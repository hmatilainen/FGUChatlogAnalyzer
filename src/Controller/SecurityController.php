<?php

namespace App\Controller;

use App\Service\FileCleanupService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Form\LoginType;
use Symfony\Bundle\SecurityBundle\Security;

class SecurityController extends AbstractController
{
    private $fileCleanupService;
    private $security;

    public function __construct(FileCleanupService $fileCleanupService, Security $security)
    {
        $this->fileCleanupService = $fileCleanupService;
        $this->security = $security;
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $form = $this->createForm(LoginType::class);
        
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'form' => $form->createView(),
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        $user = $this->security->getUser();
        if ($user) {
            $this->fileCleanupService->cleanupUserFiles($user->getUserIdentifier());
        }
        // This method can be empty - it will be intercepted by the logout key on your firewall
    }

    #[Route('/logout/confirm', name: 'app_logout_confirm')]
    public function logoutConfirm(): Response
    {
        return $this->render('security/logout_confirm.html.twig');
    }
} 