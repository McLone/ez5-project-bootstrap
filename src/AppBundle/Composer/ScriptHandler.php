<?php


namespace AppBundle\Composer;

use Composer\Script\CommandEvent;
use Sensio\Bundle\DistributionBundle\Composer\ScriptHandler as DistributionBundleScriptHandler;
use Symfony\Component\Yaml\Parser;

class ScriptHandler extends DistributionBundleScriptHandler
{
    public static function generateLegacyAutoload(CommandEvent $event) {
        $options = self::getOptions( $event );
        $appDir = $options['symfony-app-dir'];
        $env = isset( $options['ezpublish-asset-dump-env'] ) ? $options['ezpublish-asset-dump-env'] : "";

        $envParameter = '';
        if($env) {
            $envParameter =  '--env=' . escapeshellarg( $env );
        }

        static::executeCommand($event, $appDir, 'ezpublish:legacy:script ' . $envParameter . ' bin/php/ezpgenerateautoloads.php');
    }

    public static function initDb(CommandEvent $event) {
        $options = self::getOptions( $event );
        $appDir = $options['symfony-app-dir'];

        $parser = new Parser();
        $parameters = $parser->parse(file_get_contents($appDir.'/config/parameters.yml'));

        if($event->getIO()->askConfirmation("Are you sure you want to delete all data in '{$parameters['parameters']['db_dbname']}' database (and that this database is created)? ")) {
            $env = isset( $options['ezpublish-asset-dump-env'] ) ? $options['ezpublish-asset-dump-env'] : "";

            $envParameter = '';
            if($env) {
                $envParameter =  '--env=' . escapeshellarg( $env );
            }

            static::executeCommand($event, $appDir, 'ezpublish:test:init_db ' . $envParameter . ' --interactive');
        }
    }
}