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

/**
 * Controller for chatlog upload, analysis, and session/character views.
 *
 * @package App\Controller
 */
class ChatlogController extends AbstractController
{
    private ChatlogAnalyzer $chatlogAnalyzer;
    private Security $security;
    private ChatlogService $chatlogService;

    /**
     * ChatlogController constructor.
     *
     * @param ChatlogAnalyzer $chatlogAnalyzer
     * @param Security $security
     * @param ChatlogService $chatlogService
     */
    public function __construct(ChatlogAnalyzer $chatlogAnalyzer, Security $security, ChatlogService $chatlogService)
    {
        $this->chatlogAnalyzer = $chatlogAnalyzer;
        $this->security = $security;
        $this->chatlogService = $chatlogService;
    }

    /**
     * Upload a chatlog file and process it for the current user.
     *
     * @param Request $request
     * @return Response
     */
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

    /**
     * Analyze a chatlog file and render the analysis view.
     *
     * @param string $filename
     * @return Response
     */
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

    /**
     * List all chatlogs for the current user.
     *
     * @return Response
     */
    #[Route('/chatlog/list', name: 'app_chatlog_list')]
    public function list(): Response
    {
        $userId = $this->getUser()->getUserIdentifier();
        $chatlogs = $this->chatlogService->getUserChatlogs($userId);

        return $this->render('chatlog/list.html.twig', [
            'chatlogs' => $chatlogs
        ]);
    }

    /**
     * Delete a chatlog file for the current user.
     *
     * @param string $filename
     * @return Response
     */
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

    /**
     * Show analysis for a specific character in a chatlog.
     *
     * @param string $filename
     * @param string $character
     * @return Response
     */
    #[Route('/chatlog/{filename}/character/{character}', name: 'app_chatlog_character')]
    public function character(string $filename, string $character): Response
    {
        $user = $this->security->getUser();
        $userId = $user ? $user->getUserIdentifier() : null;
        
        // Debug logging
        error_log("Requested filename: " . $filename);
        error_log("User ID: " . ($userId ?? 'null'));
        
        // Try user's directory first if authenticated
        $filepath = null;
        $isOwner = false;
        if ($userId) {
            $userFilepath = $this->chatlogService->getUserDir($userId) . '/' . $filename;
            if (file_exists($userFilepath)) {
                $filepath = $userFilepath;
                $isOwner = true;
                error_log("Using user filepath: " . $userFilepath);
            }
        }
        
        // If not found in user's directory or user is not authenticated,
        // check all user directories
        if (!$filepath) {
            $uploadDir = $this->chatlogService->getUploadDir();
            if (is_dir($uploadDir)) {
                $dirs = array_diff(scandir($uploadDir), ['.', '..', 'public']);
                foreach ($dirs as $dir) {
                    $potentialPath = $uploadDir . '/' . $dir . '/' . $filename;
                    error_log("Checking potential path: " . $potentialPath);
                    if (file_exists($potentialPath)) {
                        $filepath = $potentialPath;
                        error_log("Found file in directory: " . $dir);
                        break;
                    }
                }
            }
        }
        
        // If still not found, check public directory
        if (!$filepath) {
            $publicFilepath = $this->chatlogService->getPublicDir() . '/' . $filename;
            if (file_exists($publicFilepath)) {
                $filepath = $publicFilepath;
                error_log("Using public filepath: " . $publicFilepath);
            }
        }
        
        if (!$filepath) {
            error_log("No valid filepath found for: " . $filename);
            throw $this->createNotFoundException('The chatlog file does not exist');
        }

        $analysis = $this->chatlogAnalyzer->analyze($filepath);
        
        if (!isset($analysis['totals']['characters'][$character])) {
            error_log("Character not found: " . $character);
            throw $this->createNotFoundException('Character not found in this chatlog');
        }

        return $this->render('chatlog/character.html.twig', [
            'filename' => $filename,
            'character' => $character,
            'data' => $analysis['totals']['characters'][$character],
            'sessions' => $analysis['sessions'],
            'isOwner' => $isOwner
        ]);
    }

    /**
     * Show analysis for a specific session in a chatlog.
     *
     * @param string $filename
     * @param string $date
     * @return Response
     */
    #[Route('/chatlog/{filename}/session/{date}', name: 'app_chatlog_session')]
    public function session(string $filename, string $date): Response
    {
        $user = $this->security->getUser();
        $userId = $user ? $user->getUserIdentifier() : null;
        
        // Debug logging
        error_log("Requested filename: " . $filename);
        error_log("User ID: " . ($userId ?? 'null'));
        
        // Try user's directory first if authenticated
        $filepath = null;
        $isOwner = false;
        if ($userId) {
            $userFilepath = $this->chatlogService->getUserDir($userId) . '/' . $filename;
            if (file_exists($userFilepath)) {
                $filepath = $userFilepath;
                $isOwner = true;
                error_log("Using user filepath: " . $userFilepath);
            }
        }
        
        // If not found in user's directory or user is not authenticated,
        // check all user directories
        if (!$filepath) {
            $uploadDir = $this->chatlogService->getUploadDir();
            if (is_dir($uploadDir)) {
                $dirs = array_diff(scandir($uploadDir), ['.', '..', 'public']);
                foreach ($dirs as $dir) {
                    $potentialPath = $uploadDir . '/' . $dir . '/' . $filename;
                    error_log("Checking potential path: " . $potentialPath);
                    if (file_exists($potentialPath)) {
                        $filepath = $potentialPath;
                        error_log("Found file in directory: " . $dir);
                        break;
                    }
                }
            }
        }
        
        // If still not found, check public directory
        if (!$filepath) {
            $publicFilepath = $this->chatlogService->getPublicDir() . '/' . $filename;
            if (file_exists($publicFilepath)) {
                $filepath = $publicFilepath;
                error_log("Using public filepath: " . $publicFilepath);
            }
        }
        
        if (!$filepath) {
            error_log("No valid filepath found for: " . $filename);
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
            error_log("Session not found: " . $date);
            throw $this->createNotFoundException('Session not found in this chatlog');
        }

        return $this->render('chatlog/session.html.twig', [
            'filename' => $filename,
            'date' => $date,
            'session' => $session,
            'isOwner' => $isOwner
        ]);
    }

    /**
     * List all sessions found in all chatlogs for the current user.
     *
     * @return Response
     */
    #[Route('/chatlog/sessions', name: 'app_chatlog_sessions')]
    public function sessions(): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $userId = $user->getUserIdentifier();
        $chatlogs = $this->chatlogService->getUserChatlogs($userId);
        
        if (empty($chatlogs)) {
            $this->addFlash('info', 'No chatlogs found. Please upload some chatlogs first.');
            return $this->redirectToRoute('app_chatlog_upload');
        }

        $allSessions = [];
        foreach ($chatlogs as $chatlog) {
            if (!isset($chatlog['filename'])) {
                continue;
            }
            
            $filepath = $this->chatlogService->getUserDir($userId) . '/' . $chatlog['filename'];
            if (!file_exists($filepath)) {
                continue;
            }

            try {
                $analysis = $this->chatlogAnalyzer->analyze($filepath);
                if (!isset($analysis['sessions']) || !is_array($analysis['sessions'])) {
                    continue;
                }

                foreach ($analysis['sessions'] as $session) {
                    if (!isset($session['date']) || !isset($session['time'])) {
                        continue;
                    }

                    $sessionData = array_merge($session, [
                        'filename' => $chatlog['filename'],
                        'modified' => $chatlog['modified'] ?? null
                    ]);
                    $allSessions[] = $sessionData;
                }
            } catch (\Exception $e) {
                // Log the error but continue processing other files
                continue;
            }
        }

        if (empty($allSessions)) {
            $this->addFlash('info', 'No sessions found in your chatlogs.');
            return $this->redirectToRoute('app_chatlog_list');
        }

        // Sort sessions by date and time in reverse order
        usort($allSessions, function($a, $b) {
            $dateA = strtotime($a['date'] . ' ' . $a['time']);
            $dateB = strtotime($b['date'] . ' ' . $b['time']);
            return $dateB <=> $dateA;
        });

        return $this->render('chatlog/sessions.html.twig', [
            'sessions' => $allSessions
        ]);
    }
} 