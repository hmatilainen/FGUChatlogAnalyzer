<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class CookieAuthenticator extends AbstractAuthenticator implements EventSubscriberInterface
{
    private const COOKIE_NAME = 'user_id';
    private const COOKIE_LIFETIME = 31536000; // 1 year
    private ?string $userIdToSet = null;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->userIdToSet) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->setCookie(new Cookie(
            self::COOKIE_NAME,
            $this->userIdToSet,
            time() + self::COOKIE_LIFETIME,
            '/',
            null,
            true, // secure
            true  // httpOnly
        ));
    }

    public function supports(Request $request): ?bool
    {
        // Always try to authenticate for all routes
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $userId = $request->cookies->get(self::COOKIE_NAME);
        
        // Debug logging
        error_log("Cookie user ID: " . ($userId ?? 'null'));
        
        if (!$userId) {
            $userId = bin2hex(random_bytes(16));
            $this->userIdToSet = $userId;
            error_log("Generated new user ID: " . $userId);
        }

        return new SelfValidatingPassport(
            new UserBadge($userId, function($userIdentifier) {
                error_log("Creating user with ID: " . $userIdentifier);
                return new InMemoryUser($userIdentifier, '', ['ROLE_USER']);
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // We don't need to return a response here anymore as we're using the event subscriber
        return null;
    }

    public function onAuthenticationFailure(Request $request, \Symfony\Component\Security\Core\Exception\AuthenticationException $exception): ?Response
    {
        return null;
    }
} 