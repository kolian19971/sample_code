<?php

namespace AjaxBundle\Controller;

use AjaxBundle\AjaxException;
use AjaxBundle\AjaxResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AjaxController extends AbstractController
{
    /**
     * @Route("/admin/ajax", name="ajax_admin")
     */
    public function admin(Request $request)
    {
        return $this->index($request);
    }

    /**
     * @Route("/ajax", name="ajax_index")
     */
    public function index(Request $request)
    {
        try {
            return $this->handle($request);
        } catch (AjaxException $ajaxException) {
            return new AjaxResponse([], $ajaxException->getMessage(), $ajaxException->getDetails());
        } catch (AccessDeniedException $accessDeniedException) {
            return new AjaxResponse([], 'Недостаточно прав !');
        } catch (\Exception $exception) {
            print_r($exception->__toString());
            return new AjaxResponse([], 'Произошла ошибка ! Попробуйте позже.');
        }
    }

    /**
     * @param Request $request
     * @return Response
     * @throws AjaxException
     */
    protected function handle(Request $request)
    {
        $method = $request->get('method', '');

        if (!$method)
            throw new AjaxException('Params `method` undefined');

        $method = trim($method);

        $methodParts = explode('.', $method);

        if (count($methodParts) !== 2)
            throw new AjaxException("error method {$method} not found");

        list($controllerName, $controllerMethod) = $methodParts;

        $controllerName = '\\' . ucfirst(trim($controllerName)) . 'Bundle\Controller\AjaxController';
        $controllerMethod = trim($controllerMethod) . 'Action';

        if (class_exists($controllerName) && method_exists($controllerName, $controllerMethod))
            return $this->forward("{$controllerName}:{$controllerMethod}");

        throw new AjaxException('invalid call: no such method found');
    }

    /**
     * @param string $controller
     * @param array $path
     * @param array $query
     * @return Response
     */
    protected function forward(string $controller, array $path = [], array $query = []): Response
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $path['_controller'] = $controller;
        $subRequest = $request->duplicate($query, null, $path);

        return $this->container->get('http_kernel')->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);
    }
}


?>