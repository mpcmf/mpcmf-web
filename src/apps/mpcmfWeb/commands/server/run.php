<?php

namespace mpcmf\apps\mpcmfWeb\commands\server;

use GuzzleHttp\Psr7\Uri;
use mpcmf\apps\mpcmfWeb\libraries\io\multipartParser;
use mpcmf\apps\mpcmfWeb\libraries\net\listeningSocketServer;
use mpcmf\apps\mpcmfWeb\mpcmfWeb;
use mpcmf\system\application\applicationInstance;
use mpcmf\system\application\consoleCommandBase;
use mpcmf\system\application\exception\webApplicationException;
use mpcmf\system\application\webApplicationBase;
use mpcmf\system\configuration\config;
use mpcmf\system\helper\service\signalHandler;
use mpcmf\system\helper\system\profiler;
use mpcmf\system\threads\gracefulStop;
use mpcmf\system\threads\thread;
use React\Dns\Resolver\Factory as reactResolver;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Http\Response as reactResponse;
use React\Http\Server as reactHttpServer;
use React\Http\ServerRequest;
use React\Http\UploadedFile;
use React\Promise\Promise;
use React\Socket\Connection;
use React\SocketClient\Connector;
use React\SocketClient\ConnectorInterface;
use React\SocketClient\TimeoutConnector;
use React\Stream\ReadableStreamInterface;
use React\Stream\Stream;
use React\Stream\Stream as reactStream;
use React\Stream\Util;
use Slim\Environment;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Middleware;
use Slim\Middleware\PrettyExceptions;
use Slim\Route;
use Slim\Router;
use Slim\Slim;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Async MPCMF Server
 *
 * @author Gregory Ostrovsky <greevex@gmail.com>
 */
abstract class run
    extends consoleCommandBase
{
    protected $maxMemoryUsage = 838860800; //800MiB

    protected $oomKillerEnabled = false;
    protected $checkThreadsTimerInterval = 1.0;

    /** @var applicationInstance */
    private $applicationInstance;
    private $childPorts = [];

    /** @var thread[] */
    private $threads = [];

    private $childHost;
    private $port;

    /** @var OutputInterface */
    private $output;

    /**
     * Master server: timeout of the connection to a child, seconds
     *
     * @var float
     */
    private $masterConnectTimeout = 3.0;

    /**
     * Master server sockets (listening socket, client and child connections) by resource id
     *
     * @var resource[]
     */
    private $masterResources = [];

    private $pendingConnects = 0;

    /** @var LoopInterface|null */
    private $masterLoop;

    private $masterLogState = [];

    /** @var listeningSocketServer|null */
    private $masterSocketServer;

    /**
     * Graceful stop on SIGTERM/SIGINT, see setGracefulStop()
     *
     * @var bool
     */
    private $gracefulStop = true;

    /**
     * @var float seconds
     */
    private $gracefulStopTimeout = 30.0;

    /**
     * Graceful stop is enabled and supported by mpcmf-core
     *
     * @var bool
     */
    private $gracefulStopActive = false;

    private $masterPid;
    private $childExited = false;
    private $legacyStopRequested = false;
    private $shuttingDown = false;
    private $shutdownStartedAt;
    private $childrenStopSent = false;
    private $masterFinished = false;

    /** @var LoopInterface|null */
    private $childLoop;

    /** @var listeningSocketServer|null */
    private $childSocket;

    private $openConnections = 0;
    private $childStopping = false;
    private $childStopForced = false;

    /**
     * Zero-loss recycle of children, see setGracefulRecycle()
     *
     * @var bool
     */
    private $gracefulRecycle = false;

    /**
     * Recycle a child after this many requests, 0 - no limit
     *
     * @var int
     */
    private $maxRequests = 0;

    /**
     * Listening sockets opened by the master and shared with the children, by port
     *
     * @var resource[]
     */
    private $sharedSockets = [];

    /**
     * Children tell the master they retire through this socket pair: [master end, children end]
     *
     * @var resource[]
     */
    private $notifySockets = [];

    private $notifyBuffer = '';

    /**
     * Retiring children still finishing their requests, replaced already
     *
     * @var thread[]
     */
    private $retiredThreads = [];

    private $handledRequests = 0;

    /**
     * @param float $checkThreadsTimerInterval
     */
    public function setCheckThreadsTimerInterval($checkThreadsTimerInterval)
    {
        $this->checkThreadsTimerInterval = $checkThreadsTimerInterval;
    }

    /**
     * Graceful stop, on by default (needs mpcmf\system\threads\gracefulStop in mpcmf-core, without it the old behaviour)
     *
     * Child: SIGTERM/SIGINT stop accepting, the accepted requests are finished, exit code 0.
     * Master: stops the children (SIGUSR2), waits for them without starting new ones and exits with 0.
     * A repeated SIGTERM/SIGINT exits at once.
     *
     * @param bool $enabled
     */
    public function setGracefulStop($enabled)
    {
        $this->gracefulStop = (bool)$enabled;
    }

    /**
     * Hard deadline of the graceful stop of a child, seconds; the master kills children 5 s later
     *
     * @param float $seconds
     */
    public function setGracefulStopTimeout($seconds)
    {
        $this->gracefulStopTimeout = max(1.0, (float)$seconds);
    }

    /**
     * Zero-loss recycle of children, off by default
     *
     * The master opens the listening socket of every port before forking, children accept from it. A child leaves
     * after max requests (setMaxRequests()) or when its memory usage is over the soft limit: maxMemoryUsage
     * (setMaxMemoryUsage()), at most 80% of memory_limit. It stops accepting, tells the master, finishes
     * the accepted requests and exits; the master starts the replacement on the same socket at once, new connections
     * wait in the kernel queue meanwhile. Children leave when the master is gone. The listening sockets are opened
     * at start, a busy port fails the start.
     *
     * @param bool $enabled
     */
    public function setGracefulRecycle($enabled)
    {
        $this->gracefulRecycle = (bool)$enabled;
    }

    /**
     * With graceful recycle: recycle a child after this many requests, 0 - no limit (default)
     *
     * @param int $maxRequests
     */
    public function setMaxRequests($maxRequests)
    {
        $this->maxRequests = max(0, (int)$maxRequests);
    }

    /**
     * Master server (-m): timeout of the connection to a child, the next child is tried after it
     *
     * @param float $seconds
     */
    public function setMasterConnectTimeout($seconds)
    {
        $this->masterConnectTimeout = (float)$seconds;
    }

    /**
     * Define arguments
     *
     * @return mixed
     */
    protected function defineArguments()
    {
        $this->applicationInstance = applicationInstance::getInstance();

        $this->addOption('bind', 'b', InputOption::VALUE_REQUIRED, 'Host to bind', '127.0.0.1');
        $this->addOption('ports', 'p', InputOption::VALUE_REQUIRED, 'Ports');
        $this->addOption('master-server', 'm', InputOption::VALUE_OPTIONAL, 'Start master server on this host:port');
    }

    /**
     * Executes the current command.
     *
     * This method is not because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return null|int null or 0 if everything went fine, or an error code
     *
     * @throws \LogicException When this method is not implemented
     *
     * @see setCode()
     */
    protected function handle(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $smartyDir = dirname(rtrim(config::getConfig('mpcmf_system_view_smartyDriver')['config_dir'], '/'));

        if(file_exists($smartyDir)) {
            @shell_exec("chmod -R 0777 '{$smartyDir}' && chmod -R 0777 '{$smartyDir}'");
        } else {
            @mkdir($smartyDir, 0777, true);
        }
        $this->masterPid = getmypid();
        $this->gracefulStopActive = $this->gracefulStop && class_exists(gracefulStop::class) && method_exists(thread::class, 'setGracefulStop');
        $this->prepareThreads($input);
        if($this->gracefulRecycle) {
            $this->openSharedSockets();
        }
        $this->setupMasterSignals();
        $this->checkThreads();

        $masterServerAddr = $input->getOption('master-server');
        if($masterServerAddr) {
            $this->masterServer($this->parseAddr($masterServerAddr));
        } else {
            while(!$this->masterFinished) {
                $this->checkThreads();
                // interrupted by signals: SIGCHLD, stop requests
                if(isset($this->notifySockets[0])) {
                    $read = [$this->notifySockets[0]];
                    $write = $except = null;
                    $seconds = (int)$this->checkThreadsTimerInterval;
                    @stream_select($read, $write, $except, $seconds, (int)(($this->checkThreadsTimerInterval - $seconds) * 1000000));
                } else {
                    usleep((int)($this->checkThreadsTimerInterval * 1000000));
                }
                $this->masterTick();
            }
        }
        $this->output->writeln('<error>[MASTER]</error> Stopped');

        return 0;
    }

    public function checkThreads()
    {
        foreach($this->retiredThreads as $key => $thread) {
            if(!$thread->isAlive()) {
                $this->output->writeln("<error>[MASTER]</error> Retired child {$thread->getPid()} exited with code " . json_encode($thread->getExitCode()));
                unset($this->retiredThreads[$key]);
            }
        }
        if($this->shuttingDown) {

            return;
        }
        foreach($this->threads as $addr => $thread) {
            if(!$thread->isAlive()) {
                if($thread->getPid() !== null && method_exists($thread, 'getExitCode')) {
                    $this->output->writeln("<error>[MASTER]</error> Child {$thread->getPid()} exited with code " . json_encode($thread->getExitCode()));
                }
                //MPCMF_DEBUG && $output->writeln("<error>Starting server on {$addr}</error>");
                try {
                    $thread->start($addr);
                } catch(\Exception $e) {
                    error_log("Unable to start server, cuz exception: {$e->getMessage()}\n{$e->getTraceAsString()}");
                }
                usleep(10000);
            }
        }
    }

    protected function prepareThreads(InputInterface $input)
    {

        $this->childHost = $input->getOption('bind');
        $portsString = $input->getOption('ports');
        if(empty($this->childHost) || empty($portsString)) {
            error_log('--bind & --ports required params');
            exit;
        }
        if(empty($portsString)) {
            $portsString = file_get_contents(APP_ROOT . '/.prefork_config');
        }
        $this->childPorts = [];
        foreach(explode(',', $portsString) as $port) {
            $port = trim($port);
            if(empty($port)) {
                continue;
            }
            $this->childPorts[$port] = true;
        }
        /** @var thread[] $threads */
        $this->threads = [];

        foreach($this->childPorts as $port => $value) {
            $this->threads[json_encode($this->parseAddr("{$this->childHost}:{$port}"))] = $this->createThread();
        }
    }

    /**
     * @return thread
     */
    private function createThread()
    {
        $thread = new thread([$this, 'childServer']);
        if($this->gracefulStopActive) {
            // the master stops children itself instead of killing them on SIGTERM
            $thread->setGracefulStop(true);
        }

        return $thread;
    }

    /**
     * Master: stop signals and wake up on the exit of a child
     */
    private function setupMasterSignals()
    {
        pcntl_signal(SIGCHLD, function () {
            $this->childExited = true;
        });

        if($this->gracefulStopActive) {
            gracefulStop::enable((int)ceil($this->gracefulStopTimeout) + 10);
            // the master asks children to stop with SIGUSR2: unlike a forwarded SIGTERM it does not count as
            // a repeated stop signal when the process manager signals the whole group. Inherited by forked children.
            $masterPid = $this->masterPid;
            pcntl_signal(SIGUSR2, static function () use ($masterPid) {
                if(posix_getpid() !== $masterPid) {
                    gracefulStop::request();
                }
            });
        } elseif(class_exists(signalHandler::class)) {
            // without gracefulStop in core thread::masterSignalHandler() kills the children on SIGTERM:
            // do not start them again, exit
            signalHandler::getInstance()->addHandler(SIGTERM, function () {
                $this->legacyStopRequested = true;
            });
        }
    }

    /**
     * Master: process signals, run the shutdown
     */
    private function masterTick()
    {
        pcntl_signal_dispatch();
        $this->readRetireNotifications();
        if(!$this->shuttingDown) {
            $stopRequested = $this->gracefulStopActive ? gracefulStop::isRequested() : $this->legacyStopRequested;
            if($stopRequested) {
                $this->beginShutdown();
            }
        }
        if($this->shuttingDown) {
            $this->continueShutdown();
        } elseif($this->childExited) {
            $this->childExited = false;
            $this->checkThreads();
        }
    }

    private function beginShutdown()
    {
        $this->shuttingDown = true;
        $this->shutdownStartedAt = microtime(true);
        $this->output->writeln("<error>[MASTER]</error> Stopping, waiting up to {$this->gracefulStopTimeout}s for children");
        if($this->masterSocketServer !== null) {
            $this->masterSocketServer->pause();
            $this->masterSocketServer->acceptPending();
            $this->untrackMasterResource($this->masterSocketServer->getSocket());
            $this->masterSocketServer->close();
            $this->masterSocketServer = null;
        }
        // children keep their copies until they accepted the queued connections
        foreach($this->sharedSockets as $socket) {
            if(is_resource($socket)) {
                fclose($socket);
            }
        }
        $this->sharedSockets = [];
    }

    private function continueShutdown()
    {
        $now = microtime(true);
        // connections accepted by the master proxy reach a child first
        if(!$this->childrenStopSent && ($this->pendingConnects === 0 || $now - $this->shutdownStartedAt > 1.0)) {
            $this->childrenStopSent = true;
            if($this->gracefulStopActive) {
                foreach($this->getChildThreads() as $thread) {
                    if($thread->isAlive()) {
                        posix_kill($thread->getPid(), SIGUSR2);
                    }
                }
            }
        }

        $alive = [];
        foreach($this->getChildThreads() as $thread) {
            if($thread->isAlive()) {
                $alive[] = $thread;
            }
        }
        if(empty($alive)) {
            // let the master proxy flush the last responses
            if(empty($this->masterResources) || $now - $this->shutdownStartedAt > $this->gracefulStopTimeout + 5) {
                $this->masterFinished = true;
            }

            return;
        }
        if($now - $this->shutdownStartedAt > $this->gracefulStopTimeout + 5) {
            foreach($alive as $thread) {
                $this->output->writeln("<error>[MASTER]</error> Killing child {$thread->getPid()}: did not stop in time");
                posix_kill($thread->getPid(), SIGKILL);
            }
        }
    }

    /**
     * All children the master is responsible for
     *
     * @return thread[]
     */
    private function getChildThreads()
    {
        return array_merge(array_values($this->threads), array_values($this->retiredThreads));
    }

    /**
     * Master: open the listening sockets the children share, and the retire notification channel
     *
     * @throws \RuntimeException
     */
    private function openSharedSockets()
    {
        foreach($this->childPorts as $port => $value) {
            $this->sharedSockets[$port] = listeningSocketServer::listen($this->childHost, $port);
        }
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if($pair === false) {
            throw new \RuntimeException('Unable to create the retire notification socket pair');
        }
        stream_set_blocking($pair[0], false);
        $this->notifySockets = $pair;
        $this->output->writeln('<error>[MASTER]</error> Graceful recycle: listening on ' . implode(', ', array_keys($this->sharedSockets)) . ', max requests: ' . ($this->maxRequests ?: 'unlimited') . ', memory: ' . $this->getRecycleMemoryLimit());
    }

    /**
     * Master: start replacements of retiring children
     */
    private function readRetireNotifications()
    {
        if(!isset($this->notifySockets[0])) {

            return;
        }
        while(($chunk = @fread($this->notifySockets[0], 8192)) !== false && $chunk !== '') {
            $this->notifyBuffer .= $chunk;
        }
        while(($eol = strpos($this->notifyBuffer, "\n")) !== false) {
            $line = substr($this->notifyBuffer, 0, $eol);
            $this->notifyBuffer = (string)substr($this->notifyBuffer, $eol + 1);
            [$pid, $reason] = array_pad(explode(' ', $line, 2), 2, '');
            $this->replaceRetiringChild((int)$pid, $reason);
        }
    }

    /**
     * @param int    $pid
     * @param string $reason
     */
    private function replaceRetiringChild($pid, $reason)
    {
        if($this->shuttingDown) {

            return;
        }
        foreach($this->threads as $addr => $thread) {
            if($thread->getPid() !== $pid) {
                continue;
            }
            $this->retiredThreads[] = $thread;
            $this->threads[$addr] = $this->createThread();
            try {
                $this->threads[$addr]->start($addr);
            } catch(\Exception $e) {
                error_log("Unable to start server, cuz exception: {$e->getMessage()}\n{$e->getTraceAsString()}");
            }
            $this->output->writeln("<error>[MASTER]</error> Child {$pid} retiring ({$reason}), replacement {$this->threads[$addr]->getPid()} started");

            return;
        }
    }

    /**
     * Soft memory limit of graceful recycle, bytes
     *
     * @return int
     */
    private function getRecycleMemoryLimit()
    {
        $limit = (int)$this->maxMemoryUsage;
        $memoryLimit = $this->parseIniBytes(ini_get('memory_limit'));
        if($memoryLimit > 0) {
            $limit = min($limit, (int)($memoryLimit * 0.8));
        }

        return $limit;
    }

    /**
     * @param string|false $value
     *
     * @return int bytes, -1 for no limit
     */
    private function parseIniBytes($value)
    {
        $value = trim((string)$value);
        if($value === '' || $value === '-1') {

            return -1;
        }
        $number = (int)$value;
        switch(strtolower(substr($value, -1))) {
            case 'g':
                $number *= 1024;
            // no break
            case 'm':
                $number *= 1024;
            // no break
            case 'k':
                $number *= 1024;
        }

        return $number;
    }

    protected function parseAddr($addr)
    {
        $explodedAddr = explode(':', $addr);

        return [
            'port' => array_pop($explodedAddr),
            'host' => trim(implode(':', $explodedAddr), '[]'),
        ];
    }

    public function masterServer($bindMasterTo)
    {
        cli_set_process_title("mpcmf/console server:run/master -b {$bindMasterTo['host']} -p {$bindMasterTo['port']}");

        $output = $this->output;

        $loop = Factory::create();
        $this->masterLoop = $loop;

        $dnsResolverFactory = new reactResolver();
        $dns = $dnsResolverFactory->createCached('8.8.8.8', $loop);
        $connector = new TimeoutConnector(new Connector($loop, $dns), $this->masterConnectTimeout, $loop);

        $output->writeln('<error>[MASTER]</error> Binding callables and building socketServer');

        $socketServer = new listeningSocketServer(listeningSocketServer::listen($bindMasterTo['host'], $bindMasterTo['port']), $loop, false);
        $this->trackMasterResource($socketServer->getSocket());

        $socketServer->on('connection', function (Connection $clientConnection) use ($connector) {
            $this->proxyConnection($clientConnection, $connector);
        });
        $socketServer->resume();
        $this->masterSocketServer = $socketServer;

        $output->writeln("<error>[MASTER]</error> Starting server on {$bindMasterTo['host']}:{$bindMasterTo['port']}");

        $loop->addPeriodicTimer($this->checkThreadsTimerInterval, [$this, 'checkThreads']);
        if(isset($this->notifySockets[0])) {
            $loop->addReadStream($this->notifySockets[0], function () {
                $this->masterTick();
            });
        }
        // a signal only interrupts stream_select(), stop requests and exited children are checked here
        $loop->addPeriodicTimer(0.1, function () use ($loop) {
            $this->masterTick();
            if($this->masterFinished) {
                $loop->stop();
            }
        });
        $loop->run();
    }

    /**
     * Proxy one client connection of the master server to a child
     *
     * @param Connection         $clientConnection
     * @param ConnectorInterface $connector
     */
    private function proxyConnection(Connection $clientConnection, ConnectorInterface $connector)
    {
        $clientConnection->pause();
        $clientResource = $clientConnection->stream;
        $this->trackMasterResource($clientResource);

        $state = (object)[
            'closed' => false,
            'child' => null,
            'tried' => [],
        ];
        $clientConnection->on('close', function () use ($clientResource, $state) {
            $state->closed = true;
            $this->untrackMasterResource($clientResource);
            if($state->child !== null) {
                $state->child->close();
            }
        });

        $this->connectToChild($clientConnection, $connector, $state);
    }

    /**
     * Connect the client to a live child, try the next one on error, answer 502/503 when none is left
     *
     * @param Connection         $clientConnection
     * @param ConnectorInterface $connector
     * @param \stdClass          $state
     */
    private function connectToChild(Connection $clientConnection, ConnectorInterface $connector, \stdClass $state)
    {
        $threadKey = $this->pickChild($state->tried);
        if($threadKey === null) {
            $this->answerAndClose($clientConnection, empty($state->tried) ? 503 : 502);

            return;
        }
        $state->tried[$threadKey] = true;
        $childPort = json_decode($threadKey, true)['port'];

        $this->pendingConnects++;
        $connector->create($this->childHost, $childPort)->then(function (reactStream $childStream) use ($clientConnection, $state) {
            $this->pendingConnects--;
            if($state->closed) {
                $childStream->close();

                return;
            }
            try {
                $childResource = $childStream->stream;
                $this->trackMasterResource($childResource);
                $state->child = $childStream;
                $childStream->on('close', function () use ($clientConnection, $childResource, $state) {
                    $this->untrackMasterResource($childResource);
                    $state->child = null;
                    // flush what the child sent, then close: the child ended the response or died
                    $clientConnection->end();
                });
                Util::pipe($childStream, $clientConnection, ['end' => false]);
                Util::pipe($clientConnection, $childStream, ['end' => false]);
                $clientConnection->resume();
            } catch(\Throwable $e) {
                error_log("[MASTER] proxy error: {$e->getMessage()}");
                $childStream->close();
                $clientConnection->close();
            }
        }, function ($error) use ($clientConnection, $connector, $state, $childPort) {
            $this->pendingConnects--;
            if($state->closed) {

                return;
            }
            $message = $error instanceof \Throwable ? $error->getMessage() : (string)$error;
            $this->masterLog("connect:{$childPort}", "[MASTER] unable to connect to child {$this->childHost}:{$childPort}: {$message}");
            $this->connectToChild($clientConnection, $connector, $state);
        });
    }

    /**
     * Random live child, not tried yet for this client
     *
     * @param array $tried
     *
     * @return string|null thread key
     */
    private function pickChild(array $tried)
    {
        $keys = array_keys($this->threads);
        shuffle($keys);
        $queued = null;
        foreach($keys as $threadKey) {
            if(isset($tried[$threadKey])) {
                continue;
            }
            if($this->threads[$threadKey]->isAlive()) {

                return $threadKey;
            }
            // a shared socket keeps the connection in the queue until the child is started again
            if($queued === null && isset($this->sharedSockets[json_decode($threadKey, true)['port']])) {
                $queued = $threadKey;
            }
        }

        return $queued;
    }

    /**
     * @param Connection $clientConnection
     * @param int        $code
     */
    private function answerAndClose(Connection $clientConnection, $code)
    {
        $reason = $code === 503 ? 'Service Unavailable' : 'Bad Gateway';
        $this->masterLog("answer:{$code}", "[MASTER] {$code} {$reason}: no child accepted the connection");
        $body = "{$code} {$reason}\n";
        $response = "HTTP/1.1 {$code} {$reason}\r\nContent-Type: text/plain\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n{$body}";
        // answer after the request arrived: clients drop a response sent before the request, and closing a socket
        // with unread data sends RST instead of the response
        $timer = null;
        $answer = function () use ($clientConnection, $response, &$timer) {
            if($timer !== null) {
                $timer->cancel();
                $timer = null;
            }
            $clientConnection->end($response);
        };
        $clientConnection->on('data', $answer);
        $timer = $this->masterLoop->addTimer(1.0, $answer);
        $clientConnection->resume();
    }

    /**
     * error_log() at most once a second per key, with the number of suppressed messages
     *
     * @param string $key
     * @param string $message
     */
    private function masterLog($key, $message)
    {
        $now = time();
        if(!isset($this->masterLogState[$key])) {
            $this->masterLogState[$key] = ['time' => 0, 'suppressed' => 0];
        }
        $state = &$this->masterLogState[$key];
        if($state['time'] === $now) {
            $state['suppressed']++;

            return;
        }
        error_log($message . ($state['suppressed'] > 0 ? " (+{$state['suppressed']} more since last message)" : ''));
        $state = ['time' => $now, 'suppressed' => 0];
    }

    /**
     * Remember a socket of the master server: children forked from the master loop close their copies
     *
     * @param resource $resource
     */
    private function trackMasterResource($resource)
    {
        if(is_resource($resource)) {
            $this->masterResources[(int)$resource] = $resource;
        }
    }

    /**
     * @param resource $resource
     */
    private function untrackMasterResource($resource)
    {
        unset($this->masterResources[(int)$resource]);
    }

    /**
     * In a child: close the sockets inherited from the master server
     *
     * A copy kept by the child holds the TCP connection open after the master closes it, and the client hangs.
     */
    private function closeMasterResources()
    {
        foreach($this->masterResources as $resource) {
            if(is_resource($resource)) {
                fclose($resource);
            }
        }
        $this->masterResources = [];
    }

    public function childServer($addr)
    {
        $bindTo = json_decode($addr, true);
        $this->childHost = $bindTo['host'];
        $this->port = $bindTo['port'];

        cli_set_process_title("mpcmf/console server:run/child -b {$this->childHost} -p {$this->port}");

        $this->closeMasterResources();
        $sharedSocket = $this->sharedSockets[$this->port] ?? null;
        foreach($this->sharedSockets as $port => $socket) {
            if($port != $this->port && is_resource($socket)) {
                fclose($socket);
            }
        }
        $this->sharedSockets = [];
        if(isset($this->notifySockets[0])) {
            fclose($this->notifySockets[0]);
            unset($this->notifySockets[0]);
        }
        // the master wakes up on SIGCHLD, the application in the child must see the default behaviour
        pcntl_signal(SIGCHLD, SIG_DFL);
        if($this->gracefulStopActive) {
            // a bit later than our own deadline: it fires even if the loop is blocked by a request
            gracefulStop::enable((int)ceil($this->gracefulStopTimeout) + 2);
        }

// @FIX: nobody permissions tmp
//        posix_setgid(99);
//        posix_setuid(99);
//        posix_seteuid(99);
//        posix_setegid(99);

        $loop = Factory::create();
        $this->childLoop = $loop;
        $socket = new listeningSocketServer($sharedSocket ?? listeningSocketServer::listen($this->childHost, $this->port), $loop, false);
        $this->childSocket = $socket;
        $http = new reactHttpServer(function (ServerRequest $request) {
            if(MPCMF_DEBUG) {
                // @HACK for get client address (reflection hack)
                /** @var ReadableStreamInterface $requestInput */
                $requestInput = $request->getBody()->input;
                $thief = function (ReadableStreamInterface $reflectionClass) {
                    $connection = isset($reflectionClass->input) ? $reflectionClass->input : $reflectionClass->stream->input;

                    return $connection instanceof Connection ? $connection : $connection->input;
                };
                $thief = \Closure::bind($thief, null, $requestInput);
                // @HACK for get client address (reflection hack)
                $connection = $thief($requestInput);

                $this->output->writeln("<info>[CHILD:{$this->port}]</info> New connection");
                $clientName = $connection->getRemoteAddress() . '#' . spl_object_hash($request);
                $this->output->writeln("<info>[{$clientName}] Client connected");
            }

            profiler::resetStack();

            return new Promise(function ($resolve, $reject) use ($request) {
                /** @var Stream $body */
                $body = $request->getBody();
                $requestContent = '';
                $body->on('data', function ($data) use (&$requestContent) {
                    $requestContent .= $data;
                });
                $body->on('end', function () use ($resolve, &$requestContent, $request) {
                    $resolve($this->processRequest($request, $requestContent));
                });
            });
        });
        $http->listen($socket);
        $socket->on('connection', function (Connection $connection) {
            $this->openConnections++;
            $connection->on('close', function () {
                $this->openConnections--;
                $this->afterConnectionClosed();
            });
        });
        if($this->gracefulStopActive) {
            // a signal only interrupts stream_select(), the flag is checked here
            $loop->addPeriodicTimer(0.2, function () {
                if(gracefulStop::isRequested()) {
                    $this->stopChild('stop requested');
                }
            });
        }
        if($sharedSocket !== null) {
            // an orphan would serve old code: leave when the master is gone
            $loop->addPeriodicTimer(1.0, function () {
                if(posix_getppid() !== $this->masterPid) {
                    $this->stopChild('master is gone');
                }
            });
        }
        $socket->resume();

        $this->output->writeln("<error>[CHILD]</error> Starting child server on {$this->childHost}:{$this->port}");
        $loop->run();

        if($this->childStopForced) {
            exit(1);
        }
        $this->output->writeln("<error>[CHILD]</error> Child server on {$this->childHost}:{$this->port} stopped");
    }

    /**
     * Child: a connection is closed, the response is sent
     */
    private function afterConnectionClosed()
    {
        $this->handledRequests++;
        if($this->childStopping) {
            $this->finishChildIfIdle();

            return;
        }
        if(isset($this->notifySockets[1])) {
            if($this->maxRequests > 0 && $this->handledRequests >= $this->maxRequests) {
                $this->retireChild("{$this->handledRequests} requests");

                return;
            }
            $memoryUsage = memory_get_usage(true);
            if($memoryUsage > $this->getRecycleMemoryLimit()) {
                $this->retireChild("memory usage {$memoryUsage}");
            }

            return;
        }
        if($this->oomKillerEnabled) {
            $memoryUsage = memory_get_usage(true);
            if($memoryUsage > $this->maxMemoryUsage) {
                $this->output->writeln("Child stopped, cuz exceed memory limit ({$memoryUsage})");
                $this->stopChild("memory usage {$memoryUsage} > {$this->maxMemoryUsage}");
            }
        }
    }

    /**
     * Child: stop accepting, finish the accepted connections and leave the loop
     *
     * The connections already queued by the kernel are accepted first: closing the socket would reset them.
     *
     * @param string $reason
     */
    private function stopChild($reason)
    {
        if($this->childStopping) {

            return;
        }
        $this->childStopping = true;
        $this->childSocket->pause();
        $accepted = $this->childSocket->acceptPending();
        $this->childSocket->close();
        $this->output->writeln("<error>[CHILD]</error> Stopping child server on {$this->childHost}:{$this->port} ({$reason}), connections: {$this->openConnections}, accepted from queue: {$accepted}");

        $this->childLoop->addTimer($this->gracefulStopTimeout, function () {
            $this->output->writeln("<error>[CHILD]</error> Child server on {$this->childHost}:{$this->port} did not finish in {$this->gracefulStopTimeout}s, connections left: {$this->openConnections}");
            $this->childStopForced = true;
            $this->childLoop->stop();
        });
        $this->finishChildIfIdle();
    }

    /**
     * Child with a shared socket: stop accepting, let the master start the replacement, finish the requests
     *
     * The queued connections stay in the shared socket for the replacement.
     *
     * @param string $reason
     */
    private function retireChild($reason)
    {
        if($this->childStopping) {

            return;
        }
        $this->childStopping = true;
        $this->childSocket->close();
        $pid = getmypid();
        if(@fwrite($this->notifySockets[1], "{$pid} {$reason}\n") === false) {
            error_log("[CHILD] Unable to notify the master about retirement of {$pid}");
        }
        $this->output->writeln("<error>[CHILD]</error> Child {$pid} on {$this->childHost}:{$this->port} retiring ({$reason}), connections: {$this->openConnections}");

        $this->childLoop->addTimer($this->gracefulStopTimeout, function () {
            $this->output->writeln("<error>[CHILD]</error> Child server on {$this->childHost}:{$this->port} did not finish in {$this->gracefulStopTimeout}s, connections left: {$this->openConnections}");
            $this->childStopForced = true;
            $this->childLoop->stop();
        });
        $this->finishChildIfIdle();
    }

    private function finishChildIfIdle()
    {
        if($this->childStopping && $this->openConnections <= 0) {
            $this->childLoop->stop();
        }
    }

    /**
     * Run the application for one request, never throws
     *
     * @param ServerRequest $request
     * @param string        $requestContent
     *
     * @return reactResponse
     */
    private function processRequest(ServerRequest $request, $requestContent)
    {
        $originApplication = null;
        $obLevel = ob_get_level();
        $errorHandlerGuard = null;
        $slim = null;
        $middleware = null;
        try {
            $prepareResponse = $this->prepare($request, $requestContent);
            if(!$prepareResponse['status']) {

                return $prepareResponse['response'];
            }

            $app = $this->app($requestContent);
            $slim = $app->slim();
            $middleware = $this->slimMiddleware($slim);
            $originApplication = $this->applicationInstance->getCurrentApplication();
            $this->applicationInstance->setApplication($app);
            // runRaw() leaves its error handler installed when an \Error passes through Slim
            $errorHandlerGuard = static function () {
                return false;
            };
            set_error_handler($errorHandlerGuard);
            [$status, $headers, $body] = $slim->runRaw();
            restore_error_handler();
            $errorHandlerGuard = null;
        } catch(\Throwable $e) {
            if($errorHandlerGuard !== null) {
                $this->restoreErrorHandlerUpTo($errorHandlerGuard);
            }
            // Slim::call() output buffer is left open by an \Error
            while(ob_get_level() > $obLevel && ob_end_clean()) {
            }
            $errorMessage = get_class($e) . ": {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}";
            $requestLine = ($_SERVER['REQUEST_METHOD'] ?? $request->getMethod()) . ' ' . ($_SERVER['REQUEST_URI'] ?? $request->getUri()->getPath());
            error_log("[CHILD:{$this->port}] {$requestLine}: {$errorMessage}\n{$e->getTraceAsString()}");
            $responseBody = 'ISE: check';
            if((int)ini_get('display_errors') !== 0) {
                $responseBody = "{$errorMessage}\n{$e->getTraceAsString()}";
                $this->output->writeln("<error>[CHILD:{$this->port}]</error> {$responseBody}");
            }

            return new reactResponse(500, [], $responseBody);
        } finally {
            if($slim !== null) {
                $this->resetSlimState($slim, $middleware);
            }
            if($originApplication !== null) {
                $this->applicationInstance->setApplication($originApplication);
            }
        }

        $headers = $headers->all();

        static $serverSoftware;
        if($serverSoftware === null) {
            $serverSoftware = 'MPCMF Async PHP ' . phpversion();
        }

        if (array_key_exists('HTTP_ACCEPT_ENCODING', $_SERVER) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
            $headers['Content-Encoding'] = 'gzip';
            $body = gzencode($body, 6);
        }

        $headers['X-PHP-Server'] = $serverSoftware;
        $headers['X-PHP-Server-Addr'] = "{$this->childHost}:{$this->port}";

        if (isset($headers['Set-Cookie']) && is_string($headers['Set-Cookie']) && strpos($headers['Set-Cookie'], "\n") !== false) {
            $headers['Set-Cookie'] = explode("\n", $headers['Set-Cookie']);
        }

        MPCMF_DEBUG && $this->output->writeln("<info>[CHILD:{$this->port}]</info> Connection closed");

        return new reactResponse($status, $headers, $body);
    }

    /**
     * Pop error handlers down to (and including) the guard
     *
     * @param callable $guard
     */
    private function restoreErrorHandlerUpTo($guard)
    {
        for($i = 0; $i < 32; $i++) {
            $current = set_error_handler(static function () {
                return false;
            });
            restore_error_handler();
            restore_error_handler();
            if($current === $guard || $current === null) {

                return;
            }
        }
    }

    /**
     * @param Slim $slim
     *
     * @return Middleware[]
     */
    private function slimMiddleware(Slim $slim)
    {
        return \Closure::bind(function () {
            return $this->middleware;
        }, $slim, Slim::class)();
    }

    /**
     * Undo per-request state that Slim keeps in long living objects
     *
     * - slimDriver::runRaw() adds PrettyExceptions on every call in debug mode: the middleware chain grows by one
     *   element per request. Middleware added during the request in front of the chain is removed.
     * - Route::matches() appends to Route::$paramNames on every call: every route of the request method grows
     *   on every request, routes live as long as the child.
     *
     * @param Slim              $slim
     * @param Middleware[]|null $middlewareBefore
     */
    private function resetSlimState(Slim $slim, $middlewareBefore)
    {
        if(is_array($middlewareBefore)) {
            \Closure::bind(function () use ($middlewareBefore) {
                while(count($this->middleware) > count($middlewareBefore)
                    && $this->middleware[0] instanceof PrettyExceptions
                    && !in_array($this->middleware[0], $middlewareBefore, true)) {
                    array_shift($this->middleware);
                }
            }, $slim, Slim::class)();
        }

        $router = $slim->router();
        $routes = \Closure::bind(function () {
            return $this->routes;
        }, $router, Router::class)();
        if(empty($routes)) {

            return;
        }
        \Closure::bind(static function (array $routes) {
            foreach($routes as $route) {
                $route->params = [];
                $route->paramNames = [];
                $route->paramNamesPath = [];
            }
        }, null, Route::class)($routes);
    }

    public function prepare(ServerRequest $request, $content)
    {
        static $serverSoftware, $settings;
        if($serverSoftware === null) {
            $serverSoftware = 'MPCMF Async PHP ' . phpversion();
            $settings = [
                'document_root' => APP_ROOT . '/htdocs',
            ];
        }

        $_SERVER = [];

        $now = microtime(true);
        $_SERVER['REQUEST_TIME'] = (int)$now;
        $_SERVER['REQUEST_TIME_FLOAT'] = $now;
        $GLOBALS['MPCMF_START_TIME'] = $now;

        /** @var Uri $requestUrl */
        $requestUrl = $request->getUri();
        $path = $requestUrl->getPath();

        if ($path === '/favicon.ico') {
            $response = new ReactResponse(404, [], 'FAVICON NOT FOUND! :)');
            MPCMF_DEBUG && $this->output->writeln("<info>[CHILD:{$this->port}]</info> Connection closed by favicon catch");

            return [
                'status' => false,
                'response' => $response,
                'request' => $request
            ];
        }

        $realPath = realpath($settings['document_root'] . $path);
        if($realPath !== false && strpos($realPath, $settings['document_root']) !== false && (file_exists($realPath) && !is_dir($realPath))) {
            $response = new ReactResponse(200, [
                'Content-type' => \GuzzleHttp\Psr7\MimeType::fromFilename($realPath),
                'Content-length' => filesize($realPath),
            ], file_get_contents($realPath));
            MPCMF_DEBUG && $this->output->writeln("<info>[CHILD:{$this->port}]</info> Connection closed by static");

            return [
                'status' => false,
                'response' => $response,
                'request' => $request
            ];
        }


        // Remove multipartParser after swith on 0.8 version of react/http
        $multipartParser = new multipartParser(null, 10);
        $request = $multipartParser->parse($request, $content);

        $_FILES = [];
        foreach($request->getUploadedFiles() as $filename => $fileData) {
            $tmpname = tempnam('/tmp/mpcmf/', 'upl');
            if ($fileData instanceof UploadedFile) {
                $fileContent = $fileData->getStream()->getContents();
                $type = $fileData->getClientMediaType();
                $error = $fileData->getError();
                $size = $fileData->getSize();
            } else {
                $fileContent = stream_get_contents($fileData['stream']);
                $type = $fileData['type'];
                $error = $fileData['error'];
                $size = $fileData['size'];
            }
            file_put_contents($tmpname, $fileContent);
            $_FILES[$filename] = [
                'name' => $filename,
                'type' => $type,
                'tmp_name' => $tmpname,
                'error' => $error,
                'size' => $size,
            ];
        }


        $_SERVER['DOCUMENT_ROOT'] = $settings['document_root'];

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REMOTE_PORT'] = 0;

        $_SERVER['SERVER_SOFTWARE'] = $serverSoftware;
        $_SERVER['SERVER_PROTOCOL'] = "HTTP/{$request->getProtocolVersion()}";
        $_SERVER['SERVER_NAME'] = $this->childHost;
        $_SERVER['SERVER_PORT'] = $this->port;

        $path = $requestUrl->getPath();
        $queryString = $requestUrl->getQuery();

        $_SERVER['REQUEST_URI'] = $path . (!empty($queryString) ? "?{$queryString}" : '');
        $_SERVER['REQUEST_METHOD'] = mb_strtoupper($request->getMethod());

        $_SERVER['SCRIPT_NAME'] = '/';
        $_SERVER['SCRIPT_FILENAME'] = __FILE__;

        $_SERVER['PATH_INFO'] = $path;
        $_SERVER['PHP_SELF'] = $path;

        $headers = $request->getHeaders();
        foreach($headers as $headerKey => $headerValue) {
            $_SERVER['HTTP_' . strtoupper(preg_replace('/[\-\s]/', '_', $headerKey))] = end($headerValue);
        }

        $_SERVER['QUERY_STRING'] = $queryString;

        //@todo remove on pull request merge https://github.com/reactphp/http/pull/34
        $_GET = $request->getQueryParams();

        $contentType = $request->getHeaderLine('content-type');
        if (stripos($contentType, 'application/json') !== false) {
            $_POST = [];
        } elseif (!preg_match('/boundary="?(.*)"?$/', $contentType, $matches)) {
            parse_str($content, $_POST);
        } else {
            $_POST = $request->getParsedBody();
        }

//        parse_str($queryString, $parsedGET);
//        parse_str($request->getBody(), $parsedPOST);
//        $_GET = array_replace($_GET, $parsedGET);
//        $_POST = array_replace($_POST, $parsedPOST);

        if(isset($_SERVER['HTTP_COOKIE'])) {
            parse_str($_SERVER['HTTP_COOKIE'], $_COOKIE);
        } else {
            $_COOKIE = [];
        }

        $_REQUEST = array_merge($_GET, $_POST);

        return [
            'status' => true,
            'response' => null,
            'request' => $request
        ];
    }

    /**
     * @param int $maxMemoryUsage
     */
    public function setMaxMemoryUsage($maxMemoryUsage)
    {
        $this->maxMemoryUsage = $maxMemoryUsage;
    }

    /**
     * @param bool $oomKillerEnabled
     */
    public function setOomKillerEnabled($oomKillerEnabled)
    {
        $this->oomKillerEnabled = $oomKillerEnabled;
    }

    /**
     * @param $content
     *
     * @return webApplicationBase
     *
     * @throws webApplicationException
     */
    protected function app($content)
    {
        static $app, $router;

        if($app === null) {
            $app = $this->getBaseApplication();
            //MPCMF_DEBUG && self::log()->addDebug('Before bindings call...');
            $app->beforeBindings();
            $app->processBindings();
            $env = Environment::getInstance();
            $env['slim.input'] = $content;
            $router = clone $app->slim()->router();
        } else {
            /** @var mpcmfWeb $app */
            //MPCMF_DEBUG && self::log()->addDebug('Before bindings call...');
            $app->beforeBindings();

            $env = Environment::getInstance(true);
            $env['slim.input'] = $content;
            $slim = $app->slim();
            unset($slim->request, $slim->response, $slim->router, $slim->environment);
            $slim->request = new Request($env);
            $slim->response = new Response();
            if(!isset($router)) {
                $router = $app->slim()->router();
            }
            $slim->router = clone $router;
            /** @var Route $route */
            foreach($slim->router->getNamedRoutes() as $route) {
                $route->setParams([]);
            }
            $slim->environment = $env;
        }

        return $app;
    }

    /**
     * @return webApplicationBase
     */
    abstract protected function getBaseApplication();
}
