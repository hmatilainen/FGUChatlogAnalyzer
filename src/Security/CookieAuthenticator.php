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

class CookieAuthenticator extends AbstractAuthenticator
{
    private const COOKIE_NAME = 'user_id';
    private const COOKIE_LIFETIME = 31536000; // 1 year

    public function supports(Request $request): ?bool
    {
        // Always try to authenticate for character and session pages
        if (preg_match('#^/chatlog/[^/]+/(character|session)/#', $request->getPathInfo())) {
            return true;
        }
        return true; // Always try to authenticate for other routes
    }

    public function authenticate(Request $request): Passport
    {
        $userId = $request->cookies->get(self::COOKIE_NAME);
        
        // Debug logging
        error_log("Cookie user ID: " . ($userId ?? 'null'));
        
        if (!$userId) {
            $userId = bin2hex(random_bytes(16));
            $request->attributes->set('_cookie_to_set', $userId);
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
        $userId = $request->attributes->get('_cookie_to_set');
        if ($userId) {
            $response = new Response();
            $response->headers->setCookie(new Cookie(
                self::COOKIE_NAME,
                $userId,
                time() + self::COOKIE_LIFETIME,
                '/',
                null,
                true, // secure
                true  // httpOnly
            ));
            return $response;
        }

        return null;
    }

    public function onAuthenticationFailure(Request $request, \Symfony\Component\Security\Core\Exception\AuthenticationException $exception): ?Response
    {
        return null;
    }
} 