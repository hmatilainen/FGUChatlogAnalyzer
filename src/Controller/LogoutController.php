<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Bundle\SecurityBundle\Security;
use App\Service\ChatlogService;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Controller for logout confirmation and post-logout cleanup.
 *
 * @package App\Controller
 */
class LogoutController extends AbstractController
{
    private Security $security;
    private ChatlogService $chatlogService;
    private Filesystem $filesystem;
    private SessionInterface $session;

    /**
     * LogoutController constructor.
     *
     * @param Security $security
     * @param ChatlogService $chatlogService
     * @param SessionInterface $session
     */
    public function __construct(
        Security $security, 
        ChatlogService $chatlogService,
        SessionInterface $session
    ) {
        $this->security = $security;
        $this->chatlogService = $chatlogService;
        $this->filesystem = new Filesystem();
        $this->session = $session;
    }

    /**
     * Render the logout confirmation page.
     *
     * @return Response
     */
    #[Route('/logout/confirm', name: 'app_logout_confirm')]
    public function confirm(): Response
    {
        return $this->render('logout/confirm.html.twig');
    }

    /**
     * Handle post-logout cleanup and render the logged out page.
     *
     * @return Response
     */
    #[Route('/logged-out', name: 'app_logout_logged_out')]
    public function loggedOut(): Response
    {
        // Remove user's files
        $user = $this->security->getUser();
        if ($user) {
            $userDir = $this->chatlogService->getUserDir($user->getUserIdentifier());
            if ($this->filesystem->exists($userDir)) {
                $this->filesystem->remove($userDir);
            }
        }

        // Clear the session and security context
        $this->session->clear();
        $this->security->logout(false); // Disable CSRF validation for logout

        // Clear cache for this user
        if ($user) {
            $cache = new FilesystemAdapter();
            $cache->deleteItem('user_' . $user->getUserIdentifier());
        }

        return $this->render('logout/logged_out.html.twig');
    }
} 