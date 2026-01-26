<?php

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class DebugController extends AbstractController
{
    #[Route('/debug/request', name: 'debug_request')]
    public function debugRequest(): Response
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();

        return new Response(json_encode([
            'basePath' => $request->getBasePath(),
            'baseUrl' => $request->getBaseUrl(),
            'pathInfo' => $request->getPathInfo(),
            'uri' => $request->getUri(),
            'scriptName' => $request->getScriptName(),
            'headers' => [
                'X-Forwarded-Prefix' => $request->headers->get('X-Forwarded-Prefix'),
                'X-Forwarded-Host' => $request->headers->get('X-Forwarded-Host'),
                'X-Forwarded-Proto' => $request->headers->get('X-Forwarded-Proto'),
            ],
        ], JSON_PRETTY_PRINT), 200, ['Content-Type' => 'application/json']);
    }

    #[Route('/debug/urls', name: 'debug_urls')]
    public function debugUrls(RouterInterface $router): Response
    {
        $context = $router->getContext();

        return new Response(json_encode([
            'request_context' => [
                'basePath' => $context->getBaseUrl(),
                'pathInfo' => $context->getPathInfo(),
                'method' => $context->getMethod(),
                'host' => $context->getHost(),
                'scheme' => $context->getScheme(),
                'httpPort' => $context->getHttpPort(),
                'httpsPort' => $context->getHttpsPort(),
            ],
            'generated_urls' => [
                'path_login' => $this->generateUrl('app_login'),
                'path_task_list' => $this->generateUrl('app_task_list'),
            ],
        ], JSON_PRETTY_PRINT), 200, ['Content-Type' => 'application/json']);
    }

    #[Route('/debug/assets', name: 'debug_assets')]
    public function debugAssets(): Response
    {
        return $this->render('debug/assets.html.twig');
    }
}
