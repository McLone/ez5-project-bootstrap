<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class FrontpageController extends Controller
{
    public function pageAction($locationId, $viewType, $layout = true, $params = array())
    {
        //TODO
        $params['someChildrenList'] = $this->get('frontpage.repository')->fetchSomeChildrenList(); //Exemple d'utilisation

        return $this->get('ezpublish.controller.content.view')->viewLocation($locationId, $viewType, $layout, $params);
    }
}
