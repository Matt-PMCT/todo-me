<?php

declare(strict_types=1);

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for the home page.
 */
class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        // If user is logged in, redirect to tasks
        if ($this->getUser()) {
            return $this->redirectToRoute('app_task_list');
        }

        // Otherwise redirect to login
        return $this->redirectToRoute('app_login');
    }
}
