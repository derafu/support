<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSupport;

use Derafu\Support\Date;
use Derafu\Support\Str;
use Derafu\Support\Translation\SupportTranslationResourceProvider;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Imports the actual package configuration and checks its provider both as a
 * service and through the translator of a consuming application.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    private function loadContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new YamlFileLoader($container, new FileLocator(dirname(__DIR__, 2) . '/resources/config')))
            ->load('support-services.yaml');

        return $container;
    }

    private function compile(ContainerBuilder $container): void
    {
        // Keep package services reachable so unused-private-service removal
        // cannot hide an invalid definition from the construction checks.
        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }
        foreach ($container->getAliases() as $alias) {
            $alias->setPublic(true);
        }
        $container->compile();
    }

    private function consumerTranslator(): Translator
    {
        $container = $this->loadContainer();
        // The application chooses its own translator and locale. The package
        // only contributes its resource provider through the declared tag.
        $container->register('app.translator', Translator::class)
            ->setFactory([TranslatorFactory::class, 'create'])
            ->setArguments(['es', ['en'], new TaggedIteratorArgument('derafu_translation.resource_provider')]);
        $this->compile($container);
        $translator = $container->get('app.translator');
        self::assertInstanceOf(Translator::class, $translator);

        return $translator;
    }

    public function testEveryConfiguredServiceCanBeConstructed(): void
    {
        $container = $this->loadContainer();
        $this->compile($container);
        self::assertInstanceOf(SupportTranslationResourceProvider::class, $container->get(SupportTranslationResourceProvider::class));
    }

    public function testTheTaggedProviderExposesThePackageCatalogue(): void
    {
        $container = $this->loadContainer();
        self::assertArrayHasKey(
            SupportTranslationResourceProvider::class,
            $container->findTaggedServiceIds('derafu_translation.resource_provider')
        );
        $this->compile($container);
        $provider = $container->get(SupportTranslationResourceProvider::class);
        self::assertInstanceOf(SupportTranslationResourceProvider::class, $provider);
        $directories = [...$provider->getDirectories()];
        self::assertCount(1, $directories);
        self::assertSame(realpath(dirname(__DIR__, 2) . '/resources/translations'), realpath($directories[0]));
        self::assertFileExists($directories[0] . '/errors+intl-icu.es.php');
    }

    public function testTaggedResourcesTranslateARealStringUtilityError(): void
    {
        $translator = $this->consumerTranslator();
        try {
            Str::random(0);
            self::fail('A random string with an invalid length was accepted.');
        } catch (TranslatableInvalidArgumentException $exception) {
            self::assertSame('El largo debe ser al menos 1.', $exception->trans($translator));
        }
    }

    public function testTaggedResourcesTranslateARealDateUtilityErrorWithItsParameter(): void
    {
        $translator = $this->consumerTranslator();
        try {
            Date::formatPeriodSpanish(202413);
            self::fail('A period with an invalid month was accepted.');
        } catch (TranslatableInvalidArgumentException $exception) {
            self::assertSame('Mes inválido en el período: 202413', $exception->trans($translator));
        }
    }
}
