<?php

namespace mpcmf\apps\mpcmfWeb\commands\server;

use GuzzleHttp\Psr7\Uri;
use mpcmf\apps\mpcmfWeb\libraries\io\multipartParser;
use mpcmf\apps\mpcmfWeb\mpcmfWeb;
use mpcmf\system\application\applicationInstance;
use mpcmf\system\application\consoleCommandBase;
use mpcmf\system\application\exception\webApplicationException;
use mpcmf\system\application\webApplicationBase;
use mpcmf\system\configuration\config;
use mpcmf\system\helper\system\profiler;
use mpcmf\system\threads\thread;
use React\Dns\Resolver\Factory as reactResolver;
use React\EventLoop\Factory;
use React\Http\Response as reactResponse;
use React\Http\Server as reactHttpServer;
use React\Http\ServerRequest;
use React\Http\UploadedFile;
use React\Promise\Promise;
use React\Socket\Connection;
use React\Socket\Server as reactSocketServer;
use React\SocketClient\Connector;
use React\Stream\ReadableStreamInterface;
use React\Stream\Stream;
use React\Stream\Stream as reactStream;
use Slim\Environment;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Route;
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
     * @param float $checkThreadsTimerInterval
     */
    public function setCheckThreadsTimerInterval($checkThreadsTimerInterval)
    {
        $this->checkThreadsTimerInterval = $checkThreadsTimerInterval;
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
        $this->prepareThreads($input);
        $this->checkThreads();

        $masterServerAddr = $input->getOption('master-server');
        if($masterServerAddr) {
            $this->masterServer($this->parseAddr($masterServerAddr));
        } else {
            for(;;) {
                $this->checkThreads();
                usleep($this->checkThreadsTimerInterval * 1000000);
            }
        }
    }

    public function checkThreads()
    {
        foreach($this->threads as $addr => $thread) {
            if(!$thread->isAlive()) {
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
            $this->threads[json_encode($this->parseAddr("{$this->childHost}:{$port}"))] = new thread([$this, 'childServer']);
        }
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

        //MPCMF_DEBUG && $output->writeln('<error>[MASTER]</error> Preparing server');

        $loop = Factory::create();

        $dnsResolverFactory = new reactResolver();
        $dns = $dnsResolverFactory->createCached('8.8.8.8', $loop);
        $connector = new Connector($loop, $dns);

        $output->writeln('<error>[MASTER]</error> Binding callables and building socketServer');

        $socketServer = new reactSocketServer("{$bindMasterTo['host']}:{$bindMasterTo['port']}", $loop);
        $socketServer->pause();

        $clientId = null;

        $socketServer->on('connection', function (Connection $clientConnection) use ($connector, $output, $clientId, $loop) {

            $clientConnection->pause();

            MPCMF_DEBUG && $clientId = spl_object_hash($clientConnection);
            do {
                $threadKey = array_rand($this->threads);
                if($this->threads[$threadKey]->isAlive()) {
                    break;
                }
                $loop->tick();
            } while(true);
            $childPort = json_decode($threadKey, true)['port'];

            //MPCMF_DEBUG && $output->writeln("<error>[MASTER:{$clientId}]</error> Client connected, using port {$childPort}");


            $clientConnection->on('end', function() use ($clientConnection, $clientId, $output) {
                //MPCMF_DEBUG && $output->writeln("<error>[MASTER:{$clientId}]</error> Client connection ending");
            });
            $clientConnection->on('close', function() use ($clientConnection, $clientId, $output) {
                //MPCMF_DEBUG && $output->writeln("<error>[MASTER:{$clientId}]</error> Client connection closed");
            });

            /** @var \React\Promise\FulfilledPromise|\React\Promise\Promise|\React\Promise\RejectedPromise $childConnection */
            $childConnection = $connector->create($this->childHost, $childPort);
            $childConnection->then(function (reactStream $childStream) use ($clientConnection, $childConnection, $output, $clientId) {

                $childStream->pause();

                //MPCMF_DEBUG && $output->writeln('<error>=================== ' . spl_object_hash($childStream) . ' CHILD STREAM OPEN </error>');

                $childStream->on('end', function() use ($clientConnection, $childConnection, $childStream, $output, $clientId) {
                    //MPCMF_DEBUG && $output->writeln("<error>[MASTER:{$clientId}]</error> Child closed connection");
                    //MPCMF_DEBUG && $output->writeln('<error>=================== ' . spl_object_hash($childStream) . ' CHILD STREAM CLOSE</error>');
                    $childStream->close();

                    $clientConnection->getBuffer()->on('full-drain', function() use ($clientConnection, $output, $clientId) {
                        //MPCMF_DEBUG && $output->writeln("<error>[MASTER:{$clientId}]</error> Buffer is empty, closing client connection");
                        $clientConnection->close();
                    });
                });

                $childStream->on('data', function($data) use ($clientConnection, $childConnection, $childStream, $output, $clientId) {
                    //MPCMF_DEBUG && $output->writeln("<error>[MASTER:{$clientId}]</error> Response from child received");

                    //MPCMF_DEBUG && $output->writeln("<error>[MASTER:{$clientId}]</error> Sending response to client");
                    $clientConnection->write($data);
                });

                $childStream->resume();

                $clientConnection->on('data', function ($data) use ($clientConnection, $childConnection, $output, $clientId, $childStream) {
                    //MPCMF_DEBUG && $output->writeln("<error>[MASTER:{$clientId}]</error> Client data received");

                    //MPCMF_DEBUG && $output->writeln("<error>[MASTER:{$clientId}]</error> Sending request to child");
                    $childStream->write($data);
                });

                $clientConnection->resume();
            });
        });
        $socketServer->resume();

        $output->writeln("<error>[MASTER]</error> Starting server on {$bindMasterTo['host']}:{$bindMasterTo['port']}");

        $loop->addPeriodicTimer($this->checkThreadsTimerInterval, [$this, 'checkThreads']);
        $loop->run();
    }

    public function childServer($addr)
    {
        $output = $this->output;
        $bindTo = json_decode($addr, true);
        $this->childHost = $bindTo['host'];
        $this->port = $bindTo['port'];

        cli_set_process_title("mpcmf/console server:run/child -b {$this->childHost} -p {$this->port}");

// @FIX: nobody permissions tmp
//        posix_setgid(99);
//        posix_setuid(99);
//        posix_seteuid(99);
//        posix_setegid(99);

        $loop = Factory::create();
        $socket = new reactSocketServer("{$this->childHost}:{$this->port}", $loop);
        $socket->pause();
        $http = new reactHttpServer(function (ServerRequest $request) use ($socket) {
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
            if ($this->oomKillerEnabled) {
                $connection->on('close', function($connection) use ($socket) {
                    $memoryUsage = memory_get_usage(true);
                    if ($memoryUsage > $this->maxMemoryUsage) {
                        $this->output->writeln("Child stopped, cuz exceed memory limit ({$memoryUsage})");

                        $socket->close();
                    }
                });
            }

            if(MPCMF_DEBUG) {
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
                    $prepareResponse = $this->prepare($request, $requestContent);
                    if(!$prepareResponse['status']) {
                        $resolve($prepareResponse['response']);
                        return;
                    }

                    try {
                        $app = $this->app($requestContent);
                        $slim = $app->slim();
                        $originApplication = $this->applicationInstance->getCurrentApplication();
                        $this->applicationInstance->setApplication($app);
                        $slim->call();
                    } catch(\Exception $e) {
                        $errorMessage = "Exception: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n{$e->getTraceAsString()}";
                        $this->output->writeln("<error>[CHILD:{$this->port}]</error> {$errorMessage}");
                        $resolve(new reactResponse(500, [], $errorMessage));

                        return;
                    }

                    $content = $slim->response->finalize();
                    \Slim\Http\Util::serializeCookies($content[1], $slim->response->cookies, $slim->settings);
                    $content[1] = $content[1]->all();
                    $this->applicationInstance->setApplication($originApplication);

                    static $serverSoftware;
                    if($serverSoftware === null) {
                        $serverSoftware = 'MPCMF Async PHP ' . phpversion();
                    }

                    if (array_key_exists('HTTP_ACCEPT_ENCODING', $_SERVER) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
                        $content[1]['Content-Encoding'] = 'gzip';
                        $content[2] = gzencode($content[2], 6);
                    }

                    $content[1]['X-PHP-Server'] = $serverSoftware;
                    $content[1]['X-PHP-Server-Addr'] = "{$this->childHost}:{$this->port}";

                    if (isset($content[1]['Set-Cookie']) && is_string($content[1]['Set-Cookie']) && strpos($content[1]['Set-Cookie'], "\n") !== false) {
                        $content[1]['Set-Cookie'] = explode("\n", $content[1]['Set-Cookie']);
                    }

                    $response = new ReactResponse($content[0], $content[1], $content[2]);
                    MPCMF_DEBUG && $this->output->writeln("<info>[CHILD:{$this->port}]</info> Connection closed");

                    $resolve($response);
                });
            });
        });
        $http->listen($socket);
        $socket->resume();

        $this->output->writeln("<error>[CHILD]</error> Starting child server on {$this->childHost}:{$this->port}");
        $loop->run();
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
                'Content-type' => \GuzzleHttp\Psr7\mimetype_from_filename($realPath),
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
        if (mb_stripos($contentType, 'application/json') !== false) {
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
