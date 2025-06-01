<?php

namespace App\EventListener;

use App\Service\GitInfoService;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;

/**
 * Event listener to inject Git info into Twig globals on each controller event.
 *
 * @package App\EventListener
 */
class GitInfoListener implements EventSubscriberInterface
{
    private GitInfoService $gitInfoService;
    private Environment $twig;

    /**
     * GitInfoListener constructor.
     *
     * @param GitInfoService $gitInfoService
     * @param Environment $twig
     */
    public function __construct(GitInfoService $gitInfoService, Environment $twig)
    {
        $this->gitInfoService = $gitInfoService;
        $this->twig = $twig;
    }

    /**
     * Add Git info to Twig globals on each controller event.
     *
     * @param ControllerEvent $event
     * @return void
     */
    public function onKernelController(ControllerEvent $event): void
    {
        $this->twig->addGlobal('git_info', [
            'hash' => $this->gitInfoService->getGitHash(),
            'date' => $this->gitInfoService->getGitDate(),
        ]);
    }

    /**
     * Get the events this subscriber wants to listen to.
     *
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
} 