<?php

namespace App\EventListener;

use App\Service\GitInfoService;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;

class GitInfoListener implements EventSubscriberInterface
{
    private GitInfoService $gitInfoService;
    private Environment $twig;

    public function __construct(GitInfoService $gitInfoService, Environment $twig)
    {
        $this->gitInfoService = $gitInfoService;
        $this->twig = $twig;
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $this->twig->addGlobal('git_info', [
            'hash' => $this->gitInfoService->getGitHash(),
            'date' => $this->gitInfoService->getGitDate(),
        ]);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
} 