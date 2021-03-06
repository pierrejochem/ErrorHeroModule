<?php

namespace ErrorHeroModule\Spec;

use ErrorHeroModule\Controller\ErrorPreviewController;

describe('ErrorPreviewController', function () {

    given('controller', function () {

        return new ErrorPreviewController();

    });

    describe('->exceptionAction()', function() {

        it('throw Exception', function() {

            $controller = $this->controller;
            $closure = function() use ($controller) {
                $controller->exceptionAction();
            };
            expect($closure)->toThrow(new \Exception('a sample error preview'));

        });

    });

    describe('->errorAction()', function() {

        it('Error', function() {

            skipIf(PHP_MAJOR_VERSION < 7);

            try {
                $controller = $this->controller;
                $controller->errorAction();
            } catch (\Throwable $error) {
                expect($error)->toBeAnInstanceOf(\Throwable::class);
                expect($error->getMessage())->toContain('E_NOTICE');
            }

        });

    });

});
