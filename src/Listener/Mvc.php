<?php

namespace ErrorHeroModule\Listener;

use ErrorHeroModule\Handler\Logging;
use Seld\JsonLint\JsonParser;
use Zend\Console\Console;
use Zend\Console\Response as ConsoleResponse;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Http\PhpEnvironment\Request;
use Zend\Http\PhpEnvironment\Response as HttpResponse;
use Zend\Mvc\MvcEvent;
use Zend\Text\Table;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;

class Mvc extends AbstractListenerAggregate
{
    /**
     * @var array
     */
    private $errorHeroModuleConfig;

    /**
     * @var Logging
     */
    private $logging;

    /**
     * @var PhpRenderer
     */
    private $renderer;

    private $errorType = [
        E_ERROR             => 'E_ERROR',
        E_WARNING           => 'E_WARNING',
        E_PARSE             => 'E_PARSE',
        E_NOTICE            => 'E_NOTICE',
        E_CORE_ERROR        => 'E_CORE_ERROR',
        E_CORE_WARNING      => 'E_CORE_WARNING',
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_USER_NOTICE       => 'E_USER_NOTICE',
        E_STRICT            => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED        => 'E_DEPRECATED',
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
    ];

    /**
     * @param array       $errorHeroModuleConfig
     * @param Logging     $logging
     * @param PhpRenderer $renderer
     */
    public function __construct(
        array       $errorHeroModuleConfig,
        Logging     $logging,
        PhpRenderer $renderer
    ) {
        $this->errorHeroModuleConfig = $errorHeroModuleConfig;
        $this->logging               = $logging;
        $this->renderer              = $renderer;
    }

    /**
     * @param EventManagerInterface $events
     * @param int                   $priority
     *
     * @return void
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        if ($this->errorHeroModuleConfig['enable'] === true) {
            // exceptions
            $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER_ERROR, [$this, 'exceptionError']);
            $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, [$this, 'exceptionError'], 100);

            // php errors
            $this->listeners[] = $events->attach('*', [$this, 'phpError']);
        }
    }

    /**
     * @param MvcEvent $e
     *
     * @return void
     */
    public function exceptionError(MvcEvent $e)
    {
        $exception = $e->getParam('exception');
        if (!$exception) {
            return;
        }

        $this->logging->handleException(
            $exception
        );

        $this->showDefaultViewWhenDisplayErrorSetttingIsDisabled();
    }

    /**
     * @param MvcEvent $e
     *
     * @return void
     */
    public function phpError(MvcEvent $e)
    {
        register_shutdown_function([$this, 'execOnShutdown']);
        set_error_handler([$this, 'phpErrorHandler']);
    }

    /**
     * @return void
     */
    public function execOnShutdown()
    {
        $error = error_get_last();
        if ($error && $error['type']) {
            $this->phpErrorHandler($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    /**
     * @param int    $errorType
     * @param string $errorMessage
     * @param string $errorFile
     * @param int    $errorLine
     *
     * @return void
     */
    public function phpErrorHandler($errorType, $errorMessage, $errorFile, $errorLine)
    {
        $errorTypeString = $this->errorType[$errorType];
        $errorExcluded = false;
        if ($errorLine) {
            if (in_array($errorType, $this->errorHeroModuleConfig['display-settings']['exclude-php-errors'])) {
                $errorExcluded = true;
            } else {
                $this->logging->handleError(
                    $errorType,
                    $errorMessage,
                    $errorFile,
                    $errorLine,
                    $errorTypeString
                );
            }
        }

        if ($this->errorHeroModuleConfig['display-settings']['display_errors'] === 0 || $errorExcluded) {
            error_reporting(E_ALL | E_STRICT);
            ini_set('display_errors', 0);
        }

        if (! $errorExcluded) {
            $this->showDefaultViewWhenDisplayErrorSetttingIsDisabled();
        }
    }

    /**
     * It show default view if display_errors setting = 0.
     *
     * @return void
     */
    private function showDefaultViewWhenDisplayErrorSetttingIsDisabled()
    {
        $displayErrors = $this->errorHeroModuleConfig['display-settings']['display_errors'];

        if ($displayErrors === 0) {
            if (!Console::isConsole()) {

                $response = new HttpResponse();
                $response->setStatusCode(500);

                $request          = new Request();
                $isXmlHttpRequest = $request->isXmlHttpRequest();
                if ($isXmlHttpRequest === true &&
                    isset($this->errorHeroModuleConfig['display-settings']['ajax']['message'])
                ) {
                    $content     = $this->errorHeroModuleConfig['display-settings']['ajax']['message'];
                    $contentType = ((new JsonParser())->lint($content) === null) ? 'application/problem+json' : 'text/html';

                    $response->getHeaders()->addHeaderLine('Content-type', $contentType);
                    $response->setContent($content);

                    $response->send();
                    exit(-1);
                }

                $view = new ViewModel();
                $view->setTemplate($this->errorHeroModuleConfig['display-settings']['template']['view']);

                $layout = new ViewModel();
                $layout->setTemplate($this->errorHeroModuleConfig['display-settings']['template']['layout']);
                $layout->setVariable('content', $this->renderer->render($view));

                $response->getHeaders()->addHeaderLine('Content-type', 'text/html');
                $response->setContent($this->renderer->render($layout));

                $response->send();
                exit(-1);

            }

            $response = new ConsoleResponse();
            $response->setErrorLevel(-1);

            $table = new Table\Table([
                'columnWidths' => [150],
            ]);
            $table->setDecorator('ascii');
            $table->appendRow([$this->errorHeroModuleConfig['display-settings']['console']['message']]);

            $response->setContent($table->render());
            $response->send();

        }
    }
}
