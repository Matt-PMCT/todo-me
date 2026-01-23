<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;

/**
 * Controller for web authentication (login/register).
 */
class SecurityController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
    ) {
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // If user is already logged in, redirect to tasks
        if ($this->getUser()) {
            return $this->redirectToRoute('app_task_list');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserAuthenticatorInterface $userAuthenticator,
        FormLoginAuthenticator $formLoginAuthenticator,
    ): Response {
        // If user is already logged in, redirect to tasks
        if ($this->getUser()) {
            return $this->redirectToRoute('app_task_list');
        }

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));
            $password = (string) $request->request->get('password', '');
            $passwordConfirm = (string) $request->request->get('password_confirm', '');
            $csrfToken = (string) $request->request->get('_csrf_token', '');

            $errors = [];

            // Validate CSRF token
            if (!$this->isCsrfTokenValid('register', $csrfToken)) {
                $errors[] = 'Invalid security token. Please try again.';
            }

            // Validate email
            if (empty($email)) {
                $errors[] = 'Email is required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            } elseif ($this->userService->findByEmail($email) !== null) {
                $errors[] = 'An account with this email already exists.';
            }

            // Validate password
            if (empty($password)) {
                $errors[] = 'Password is required.';
            } elseif (strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters long.';
            }

            // Validate password confirmation
            if ($password !== $passwordConfirm) {
                $errors[] = 'Passwords do not match.';
            }

            if (empty($errors)) {
                // Create the user
                $user = $this->userService->register($email, $password);

                // Auto-login the user after registration
                $response = $userAuthenticator->authenticateUser(
                    $user,
                    $formLoginAuthenticator,
                    $request,
                );

                if ($response !== null) {
                    return $response;
                }

                $this->addFlash('success', 'Welcome! Your account has been created successfully.');

                return $this->redirectToRoute('app_task_list');
            }

            return $this->render('security/register.html.twig', [
                'errors' => $errors,
                'email' => $email,
            ]);
        }

        return $this->render('security/register.html.twig');
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        // This method will be intercepted by the logout key on your firewall
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
