<?php

namespace ErrorHeroModule\Spec;

use ErrorHeroModule\Middleware\Expressive;
use ErrorHeroModule\Middleware\Routed\Preview\ErrorPreviewAction;
use Zend\ConfigAggregator\ConfigAggregator;
use Zend\ConfigAggregator\PhpFileProvider;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;
use Zend\Diactoros\ServerRequest;
use Zend\Expressive\Application;
use Zend\Expressive\Helper\ServerUrlMiddleware;
use Zend\Expressive\Helper\UrlHelperMiddleware;
use Zend\Expressive\Middleware\ImplicitHeadMiddleware;
use Zend\Expressive\Middleware\ImplicitOptionsMiddleware;
use Zend\Expressive\Middleware\NotFoundHandler;
use Zend\ServiceManager\Config;
use Zend\ServiceManager\ServiceManager;

describe('Integration via ErrorPreviewAction Middleware for display errors', function () {

    given('app', function () {

        $aggregator = new ConfigAggregator([
            \Zend\Log\ConfigProvider::class,
            \Zend\Mail\ConfigProvider::class,
            \Zend\Form\ConfigProvider::class,
            \Zend\InputFilter\ConfigProvider::class,
            \Zend\Filter\ConfigProvider::class,
            \Zend\Hydrator\ConfigProvider::class,
            \Zend\Db\ConfigProvider::class,
            \Zend\Router\ConfigProvider::class,
            \Zend\Validator\ConfigProvider::class,

            new PhpFileProvider(__DIR__ . '/../Fixture/config/autoload-expressive-display-errors/{{,*.}global,{,*.}local}.php'),

        ]);

        $config =  $aggregator->getMergedConfig();

        // Build container
        $container = new ServiceManager();
        (new Config($config['dependencies']))->configureServiceManager($container);

        // Inject config
        $container->setService('config', $config);

        $app = $container->get(Application::class);
        $app->pipe(Expressive::class);
        $app->pipe(ServerUrlMiddleware::class);

        $app->pipeRoutingMiddleware();
        $app->pipe(ImplicitHeadMiddleware::class);
        $app->pipe(ImplicitOptionsMiddleware::class);
        $app->pipe(UrlHelperMiddleware::class);

        $app->pipeDispatchMiddleware();

        // At this point, if no Response is return by any middleware, the
        // NotFoundHandler kicks in; alternately, you can provide other fallback
        // middleware to execute.
        $app->pipe(NotFoundHandler::class);

        $app->get('/error-preview[/:action]', ErrorPreviewAction::class, 'error-preview');

        $db           = $container->get('Zend\Db\Adapter\Adapter');
        $tableGateway = new TableGateway('log', $db, null, new ResultSet());
        $tableGateway->delete([]);

        return $app;

    });

    describe('/error-preview', function() {

        it('show error page', function() {

            skipIf(PHP_MAJOR_VERSION < 7);

            $serverRequest = new ServerRequest([], [], '/error-preview', 'GET');
            try {
                $this->app->run($serverRequest);
            } catch (\Throwable $t) {
                expect($t)->toBeAnInstanceOf(\Throwable::class);
            }

        });

    });

    describe('/error-preview/error', function() {

        it('show error page', function() {

            skipIf(PHP_MAJOR_VERSION < 7);

            $serverRequest = new ServerRequest([], [], '/error-preview/error', 'GET');

            try {
                $this->app->run($serverRequest);
            } catch (\Throwable $t) {
                expect($t)->toBeAnInstanceOf(\Throwable::class);
            }

        });
    });

});
