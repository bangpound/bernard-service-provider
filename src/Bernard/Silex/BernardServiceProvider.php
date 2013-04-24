<?php

namespace Bernard\Silex;

use JMS\Serializer\SerializerBuilder;
use Bernard\Connection\PredisConnection;
use Bernard\Consumer;
use Bernard\Producer;
use Bernard\QueueFactory\InMemoryFactory;
use Bernard\QueueFactory\PersistentFactory;
use Bernard\Serializer\JMSSerializer;
use Bernard\ServiceResolver\PimpleAwareResolver;
use Silex\Application;

/**
 * @package Bernard
 */
class BernardServiceProvider implements \Silex\ServiceProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function register(Application $app)
    {
        $app['serializer.builder'] = $app->share(function () {
            $r = new \ReflectionClass('Bernard\Connection');

            $builder = SerializerBuilder::create();
            $builder->addMetadataDir(dirname($r->getFilename()) . '/Resources/serializer', 'Bernard');

            return $builder;
        });

        $app['serializer'] = $app->share(function ($app) {
            return $app['serializer.builder']->build();
        });

        $app['bernard.serializer'] = $app->share(function ($app) {
            return new JMSSerializer($app['serializer']);
        });

        $app['bernard.predis'] = $app->share(function ($app) {
            return $app['predis'];
        });

        $app['bernard.connection'] = $app->share(function ($app) {
            return new PredisConnection($app['bernard.predis']);
        });

        $app['bernard.queue_factory.real'] = $app->share(function ($app) {
            return new PersistentFactory($app['bernard.connection'], $app['bernard.serializer']);
        });

        $app['bernard.queue_factory.in_memory'] = $app->share(function ($app) {
            return new InMemoryFactory();
        });

        $app['bernard.consumer'] = $app->share(function ($app) {
            return new Consumer($app['bernard.service_resolver'], $app['bernard.queue_factory']->create('failed'));
        });

        $app['bernard.producer'] = $app->share(function ($app) {
            return new Producer($app['bernard.queue_factory']);
        });

        $app['bernard.service_resolver'] = $app->share(function ($app) {
            return new PimpleAwareResolver($app);
        });

        $app['bernard.queue_factory'] = function ($app) {
            if ($app['debug']) {
                return $app['bernard.queue_factory.in_memory');
            }

            return $app['bernard.queue_factory.in_memory');
        };
    }

    /**
     * {@inheritDoc}
     */
    public function boot(Application $app)
    {
    }
}
