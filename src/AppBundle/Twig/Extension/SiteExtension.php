<?php


namespace AppBundle\Twig\Extension;


use Symfony\Component\HttpFoundation\Request;

class SiteExtension extends \Twig_Extension
{
    /** @var Request|null */
    private $request = null;

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'site';
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('fix_assetic_path', array( $this, 'fixAsseticPath' ), array('needs_context' => true)),
        );
    }

    public function fixAsseticPath($context, $path)
    {
        if(
            isset($context['assetic']['debug'])
            && $context['assetic']['debug']
            && $this->request
        ) {
            $scriptName = $this->request->server->get('SCRIPT_NAME');
            if(
                substr($path, 0, strlen(dirname($scriptName))) == dirname($scriptName)
                && substr($path, 0, strlen($scriptName)) != $scriptName
            ) {
                $path = $scriptName.substr($path, strlen(dirname($scriptName)));
            }
        }
        return $path;
    }

    public function setRequest(Request $request = null) {
        $this->request = $request;
    }
}