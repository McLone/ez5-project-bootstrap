<?php


namespace AppBundle\EzRepository\Exception;


class ContentNotFoundException extends \Exception
{

    public function __construct($contentId = null, $customMessage = null, $previousException = null)
    {
        parent::__construct(
            $customMessage ?: 'Could not find content ('.($contentId ?: 'No content ID given').')',
            0,
            $previousException
        );
    }
}