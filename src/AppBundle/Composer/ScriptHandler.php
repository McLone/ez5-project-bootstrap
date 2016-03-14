<?php


namespace AppBundle\Composer;

use Composer\Script\CommandEvent;
use Sensio\Bundle\DistributionBundle\Composer\ScriptHandler as DistributionBundleScriptHandler;

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
}