<?php


namespace AppBundle\EzRepository\Exception;


class LocationNotFoundException extends \Exception
{

    public function __construct($locationId = null, $customMessage = null, $previousException = null)
    {
        parent::__construct(
            $customMessage ?: 'Could not find location ('.($locationId ?: 'No location ID given').')',
            0,
            $previousException
        );
    }
}