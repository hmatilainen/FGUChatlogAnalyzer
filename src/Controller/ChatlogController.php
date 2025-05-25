<?php

namespace App\Controller;

use App\Form\ChatlogFileType;
use App\Service\ChatlogAnalyzer;
use App\Validator\Constraints\ChatlogFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bundle\SecurityBundle\Security;
use App\Form\ChatlogUploadType;
use App\Service\ChatlogService;

class ChatlogController extends AbstractController
{
    private ChatlogAnalyzer $chatlogAnalyzer;
    private Security $security;
    private ChatlogService $chatlogService;

    public function __construct(ChatlogAnalyzer $chatlogAnalyzer, Security $security, ChatlogService $chatlogService)
    {
        $this->chatlogAnalyzer = $chatlogAnalyzer;
        $this->security = $security;
        $this->chatlogService = $chatlogService;
    }

    #[Route('/chatlog/upload', name: 'app_chatlog_upload')]
    public function upload(Request $request): Response
    {
        $form = $this->createForm(ChatlogUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            $userId = $this->getUser()->getUserIdentifier();
            
            $result = $this->chatlogService->processUpload($file, $userId);
            
            if ($result['success']) {
                $this->addFlash('success', 'Chatlog uploaded and processed successfully!');
                return $this->redirectToRoute('app_chatlog_list');
            } else {
                $this->addFlash('error', $result['error']);
            }
        }

        return $this->render('chatlog/upload.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/chatlog/analyze/{filename}', name: 'app_chatlog_analyze', methods: ['GET'])]
    public function analyze(string $filename): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $userId = $user->getUserIdentifier();
        $filepath = $this->chatlogService->getUserDir($userId) . '/' . $filename;

        if (!file_exists($filepath)) {
            throw $this->createNotFoundException('The chatlog file does not exist');
        }

        $analysis = $this->chatlogAnalyzer->analyze($filepath);
        return $this->render('chatlog/analyze.html.twig', [
            'filename' => $filename,
            'modified' => filemtime($filepath),
            'analysis' => $analysis
        ]);
    }

    #[Route('/chatlog/list', name: 'app_chatlog_list')]
    public function list(): Response
    {
        $userId = $this->getUser()->getUserIdentifier();
        $chatlogs = $this->chatlogService->getUserChatlogs($userId);

        return $this->render('chatlog/list.html.twig', [
            'chatlogs' => $chatlogs
        ]);
    }

    #[Route('/chatlog/delete/{filename}', name: 'app_chatlog_delete', methods: ['DELETE'])]
    public function delete(string $filename): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $userId = $user->getUserIdentifier();
        $filepath = $this->chatlogService->getUserDir($userId) . '/' . $filename;

        if (!file_exists($filepath)) {
            return $this->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        unlink($filepath);
        return $this->json(['success' => true]);
    }

    #[Route('/chatlog/{filename}/character/{character}', name: 'app_chatlog_character')]
    public function character(string $filename, string $character): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $userId = $user->getUserIdentifier();
        $filepath = $this->chatlogService->getUserDir($userId) . '/' . $filename;

        if (!file_exists($filepath)) {
            throw $this->createNotFoundException('The chatlog file does not exist');
        }

        $analysis = $this->chatlogAnalyzer->analyze($filepath);
        
        if (!isset($analysis['totals']['characters'][$character])) {
            throw $this->createNotFoundException('Character not found in this chatlog');
        }

        return $this->render('chatlog/character.html.twig', [
            'filename' => $filename,
            'character' => $character,
            'data' => $analysis['totals']['characters'][$character],
            'sessions' => $analysis['sessions']
        ]);
    }

    #[Route('/chatlog/{filename}/session/{date}', name: 'app_chatlog_session')]
    public function session(string $filename, string $date): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $userId = $user->getUserIdentifier();
        $filepath = $this->chatlogService->getUserDir($userId) . '/' . $filename;

        if (!file_exists($filepath)) {
            throw $this->createNotFoundException('The chatlog file does not exist');
        }

        $analysis = $this->chatlogAnalyzer->analyze($filepath);
        
        $session = null;
        foreach ($analysis['sessions'] as $s) {
            if ($s['date'] === $date) {
                $session = $s;
                break;
            }
        }

        if (!$session) {
            throw $this->createNotFoundException('Session not found in this chatlog');
        }

        return $this->render('chatlog/session.html.twig', [
            'filename' => $filename,
            'date' => $date,
            'session' => $session
        ]);
    }
} 