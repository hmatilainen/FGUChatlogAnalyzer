<?php

namespace App\Controller;

use App\Service\FileCleanupService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    private $fileCleanupService;

    public function __construct(FileCleanupService $fileCleanupService)
    {
        $this->fileCleanupService = $fileCleanupService;
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(Request $request): Response
    {
        $user = $this->getUser();
        if ($user) {
            $this->fileCleanupService->cleanupUserFiles($user->getUserIdentifier());
        }
        
        $response = new Response();
        $response->headers->clearCookie('user_id', '/', null, true, true);
        
        return $this->redirectToRoute('app_home');
    }

    #[Route('/logout/confirm', name: 'app_logout_confirm')]
    public function logoutConfirm(): Response
    {
        return $this->render('security/logout_confirm.html.twig');
    }
} 