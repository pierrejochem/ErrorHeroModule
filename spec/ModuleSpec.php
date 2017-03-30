<?php

namespace ErrorHeroModule\Spec;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDOMySql\Driver;
use Doctrine\ORM\EntityManager;
use ErrorHeroModule\Module;
use Kahlan\Plugin\Double;
use Zend\EventManager\EventManagerInterface;
use Zend\ModuleManager\Listener\ConfigListener;
use Zend\ModuleManager\ModuleEvent;
use Zend\ModuleManager\ModuleManager;
use Zend\ServiceManager\ServiceLocatorInterface;

describe('Module', function () {

    given('module', function() {
        return new Module();
    });

    describe('->getConfig()', function () {

        it('return "config" array', function () {

            $moduleConfig = include __DIR__.'/../config/module.config.php';

            $actual = $this->module->getConfig();
            expect($actual)->toBe($moduleConfig);

        });

    });

    describe('->init()', function () {

        it('receive ModuleManager that get Eventmanager that attach ModuleEvent::EVENT_LOAD_MODULES_POST', function () {

            $moduleManager = Double::instance(['extends' => ModuleManager::class, 'methods' => '__construct']);
            $eventManager    = Double::instance(['implements' => EventManagerInterface::class]);

            allow($moduleManager)->toReceive('getEventManager')->andReturn($eventManager);
            expect($eventManager)->toReceive('attach')->with(ModuleEvent::EVENT_LOAD_MODULES_POST, [$this->module, 'convertDoctrineToZendDbService']);
            expect($eventManager)->toReceive('attach')->with(ModuleEvent::EVENT_LOAD_MODULES_POST, [$this->module, 'errorPreviewPageHandler'], 101);

            $this->module->init($moduleManager);

        });

    });

    describe('->convertDoctrineToZendDbService()', function () {

        it('does not has EntityManager service', function () {

            $moduleEvent = Double::instance(['extends' => ModuleEvent::class, 'methods' => '__construct']);
            $serviceManager  = Double::instance(['implements' => ServiceLocatorInterface::class]);
            allow($moduleEvent)->toReceive('getParam')->with('ServiceManager')->andReturn($serviceManager);
            allow($serviceManager)->toReceive('has')->with(EntityManager::class)->andReturn(false);

            $this->module->convertDoctrineToZendDbService($moduleEvent);
            expect($moduleEvent)->not->toReceive('getConfigListener');

        });

        it('has EntityManager service but already has db config', function () {

            $moduleEvent = Double::instance(['extends' => ModuleEvent::class, 'methods' => '__construct']);
            $serviceManager  = Double::instance(['implements' => ServiceLocatorInterface::class]);
            allow($moduleEvent)->toReceive('getParam')->with('ServiceManager')->andReturn($serviceManager);
            allow($serviceManager)->toReceive('has')->with(EntityManager::class)->andReturn(true);

            $configListener = Double::instance(['extends' => ConfigListener::class, 'methods' => '__construct']);
            allow($moduleEvent)->toReceive('getConfigListener')->andReturn($configListener);
            allow($configListener)->toReceive('getMergedConfig')->andReturn([
                'db' => [
                    'username' => 'root',
                    'password' => '',
                    'driver'   => 'pdo_mysql',
                    'database' => 'mydb',
                    'host'     => 'localhost',
                ],
            ]);

            $this->module->convertDoctrineToZendDbService($moduleEvent);
            expect($serviceManager)->not->toReceive('get')->with(EntityManager::class);

        });

        it('has EntityManager service but already does not has db config not isset driverOptions', function () {

            $moduleEvent = Double::instance(['extends' => ModuleEvent::class, 'methods' => '__construct']);
            $serviceManager  = Double::instance(['implements' => ServiceLocatorInterface::class]);
            allow($moduleEvent)->toReceive('getParam')->with('ServiceManager')->andReturn($serviceManager);
            allow($serviceManager)->toReceive('has')->with(EntityManager::class)->andReturn(true);

            $configListener = Double::instance(['extends' => ConfigListener::class, 'methods' => '__construct']);
            allow($moduleEvent)->toReceive('getConfigListener')->andReturn($configListener);
            allow($configListener)->toReceive('getMergedConfig')->andReturn([]);

            $entityManager = Double::instance(['extends' => EntityManager::class, 'methods' => '__construct']);
            $connection    = Double::instance(['extends' => Connection::class, 'methods' => '__construct']);

            $driver = Double::instance(['extends' => Driver::class, 'methods' => '__construct']);
            allow($driver)->toReceive('getName')->andReturn('pdo_mysql');

            allow($connection)->toReceive('getParams')->andReturn([]);
            allow($connection)->toReceive('getUsername')->andReturn('root');
            allow($connection)->toReceive('getPassword')->andReturn('');
            allow($connection)->toReceive('getDriver')->andReturn($driver);
            allow($connection)->toReceive('getDatabase')->andReturn('mydb');
            allow($connection)->toReceive('getHost')->andReturn('localhost');
            allow($connection)->toReceive('getPort')->andReturn('3306');

            allow($entityManager)->toReceive('getConnection')->andReturn($connection);
            allow($serviceManager)->toReceive('get')->with(EntityManager::class)->andReturn($entityManager);

            $this->module->convertDoctrineToZendDbService($moduleEvent);
            expect($serviceManager)->toReceive('get')->with(EntityManager::class);

        });

        it('has EntityManager service but already does not has db config with isset driverOptions', function () {

            $moduleEvent = Double::instance(['extends' => ModuleEvent::class, 'methods' => '__construct']);
            $serviceManager  = Double::instance(['implements' => ServiceLocatorInterface::class]);
            allow($moduleEvent)->toReceive('getParam')->with('ServiceManager')->andReturn($serviceManager);
            allow($serviceManager)->toReceive('has')->with(EntityManager::class)->andReturn(true);

            $configListener = Double::instance(['extends' => ConfigListener::class, 'methods' => '__construct']);
            allow($moduleEvent)->toReceive('getConfigListener')->andReturn($configListener);
            allow($configListener)->toReceive('getMergedConfig')->andReturn([]);

            $entityManager = Double::instance(['extends' => EntityManager::class, 'methods' => '__construct']);
            $connection    = Double::instance(['extends' => Connection::class, 'methods' => '__construct']);

            $driver = Double::instance(['extends' => Driver::class, 'methods' => '__construct']);
            allow($driver)->toReceive('getName')->andReturn('pdo_mysql');

            allow($connection)->toReceive('getParams')->andReturn([
                'driverOptions' => [
                    \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'',
                ],
            ]);
            allow($connection)->toReceive('getUsername')->andReturn('root');
            allow($connection)->toReceive('getPassword')->andReturn('');
            allow($connection)->toReceive('getDriver')->andReturn($driver);
            allow($connection)->toReceive('getDatabase')->andReturn('mydb');
            allow($connection)->toReceive('getHost')->andReturn('localhost');
            allow($connection)->toReceive('getPort')->andReturn('3306');

            allow($entityManager)->toReceive('getConnection')->andReturn($connection);
            allow($serviceManager)->toReceive('get')->with(EntityManager::class)->andReturn($entityManager);

            $this->module->convertDoctrineToZendDbService($moduleEvent);
            expect($serviceManager)->toReceive('get')->with(EntityManager::class);

        });

    });

    describe('->errorPreviewPageHandler()', function () {

        it('does not has enable-error-preview-page', function () {

            $moduleEvent = Double::instance(['extends' => ModuleEvent::class, 'methods' => '__construct']);
            $serviceManager  = Double::instance(['implements' => ServiceLocatorInterface::class]);
            allow($moduleEvent)->toReceive('getParam')->with('ServiceManager')->andReturn($serviceManager);

            $configListener = Double::instance(['extends' => ConfigListener::class, 'methods' => '__construct']);
            allow($moduleEvent)->toReceive('getConfigListener')->andReturn($configListener);
            allow($configListener)->toReceive('getMergedConfig')->andReturn([
                'error-hero-module' => [
                    'enable' => true,
                ],
            ]);

            $this->module->errorPreviewPageHandler($moduleEvent);
            expect($configListener)->not->toReceive('setMergedConfig');

        });

        it('has enable-error-preview-page and enabled', function () {

            $moduleEvent = Double::instance(['extends' => ModuleEvent::class, 'methods' => '__construct']);
            $serviceManager  = Double::instance(['implements' => ServiceLocatorInterface::class]);
            allow($moduleEvent)->toReceive('getParam')->with('ServiceManager')->andReturn($serviceManager);

            $configListener = Double::instance(['extends' => ConfigListener::class, 'methods' => '__construct']);
            allow($moduleEvent)->toReceive('getConfigListener')->andReturn($configListener);
            allow($configListener)->toReceive('getMergedConfig')->andReturn([
                'error-hero-module' => [
                    'enable' => true,
                    'enable-error-preview-page' => true,
                ],
            ]);

            $this->module->errorPreviewPageHandler($moduleEvent);
            expect($configListener)->not->toReceive('setMergedConfig');

        });

        it('has enable-error-preview-page and disabled', function () {

            $moduleEvent = Double::instance(['extends' => ModuleEvent::class, 'methods' => '__construct']);
            $serviceManager  = Double::instance(['implements' => ServiceLocatorInterface::class]);
            allow($moduleEvent)->toReceive('getParam')->with('ServiceManager')->andReturn($serviceManager);

            $configListener = Double::instance(['extends' => ConfigListener::class, 'methods' => '__construct']);
            allow($moduleEvent)->toReceive('getConfigListener')->andReturn($configListener);
            allow($configListener)->toReceive('getMergedConfig')->andReturn([
                'error-hero-module' => [
                    'enable' => true,
                    'enable-error-preview-page' => false,
                ],
            ]);

            $this->module->errorPreviewPageHandler($moduleEvent);
            expect($configListener)->toReceive('setMergedConfig');

        });

    });

});
