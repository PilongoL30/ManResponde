<?php
/**
 * User Controller
 * Handles all user-related actions (verification, management)
 */

require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../includes/View.php';

class UserController {
    private $userService;
    
    public function __construct() {
        $this->userService = new UserService();
    }
    
    /**
     * Get all users (AJAX)
     */
    public function getAllUsers(): void {
        try {
            $users = $this->userService->getAllUsers();
            View::json(['success' => true, 'users' => $users, 'count' => count($users)]);
        } catch (Exception $e) {
            View::json([
                'success' => false,
                'error' => DEBUG_MODE ? $e->getMessage() : 'Failed to load users'
            ], 500);
        }
    }
    
    /**
     * Get pending users (AJAX)
     */
    public function getPendingUsers(): void {
        try {
            $users = $this->userService->getPendingUsers();
            View::json(['success' => true, 'users' => $users, 'count' => count($users)]);
        } catch (Exception $e) {
            View::json([
                'success' => false,
                'error' => DEBUG_MODE ? $e->getMessage() : 'Failed to load pending users'
            ], 500);
        }
    }
    
    /**
     * Get verified users (AJAX)
     */
    public function getVerifiedUsers(): void {
        try {
            $users = $this->userService->getVerifiedUsers();
            View::json(['success' => true, 'users' => $users, 'count' => count($users)]);
        } catch (Exception $e) {
            View::json([
                'success' => false,
                'error' => DEBUG_MODE ? $e->getMessage() : 'Failed to load verified users'
            ], 500);
        }
    }
    
    /**
     * Verify or unverify a user (AJAX)
     */
    public function verifyUser(): void {
        $uid = $_POST['uid'] ?? null;
        $verify = $_POST['verify'] ?? '1';
        
        if (!$uid) {
            View::json(['success' => false, 'error' => 'User ID required'], 400);
            return;
        }
        
        try {
            $isVerified = ($verify === '1' || $verify === 'true' || $verify === true);
            $success = $this->userService->setUserVerificationStatus($uid, $isVerified);
            
            if ($success) {
                View::json([
                    'success' => true,
                    'message' => $isVerified ? 'User verified successfully' : 'User unverified successfully'
                ]);
            } else {
                View::json(['success' => false, 'error' => 'Failed to update user status'], 500);
            }
        } catch (Exception $e) {
            View::json([
                'success' => false,
                'error' => DEBUG_MODE ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }
    
    /**
     * Delete a user (AJAX)
     */
    public function deleteUser(): void {
        $uid = $_POST['uid'] ?? null;
        
        if (!$uid) {
            View::json(['success' => false, 'error' => 'User ID required'], 400);
            return;
        }
        
        try {
            $success = $this->userService->deleteUser($uid);
            
            if ($success) {
                View::json(['success' => true, 'message' => 'User deleted successfully']);
            } else {
                View::json(['success' => false, 'error' => 'Failed to delete user'], 500);
            }
        } catch (Exception $e) {
            View::json([
                'success' => false,
                'error' => DEBUG_MODE ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }
    
    /**
     * Update user profile (AJAX)
     */
    public function updateProfile(): void {
        $uid = $_POST['uid'] ?? null;
        
        if (!$uid) {
            View::json(['success' => false, 'error' => 'User ID required'], 400);
            return;
        }
        
        $data = [];
        
        if (isset($_POST['displayName'])) {
            $data['displayName'] = $_POST['displayName'];
        }
        
        if (isset($_POST['email'])) {
            $data['email'] = $_POST['email'];
        }
        
        if (isset($_POST['password'])) {
            $data['password'] = $_POST['password'];
        }
        
        if (empty($data)) {
            View::json(['success' => false, 'error' => 'No data to update'], 400);
            return;
        }
        
        try {
            $success = $this->userService->updateUserProfile($uid, $data);
            
            if ($success) {
                View::json(['success' => true, 'message' => 'Profile updated successfully']);
            } else {
                View::json(['success' => false, 'error' => 'Failed to update profile'], 500);
            }
        } catch (Exception $e) {
            View::json([
                'success' => false,
                'error' => DEBUG_MODE ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }
    
    /**
     * Get user statistics (AJAX)
     */
    public function getStats(): void {
        try {
            $stats = $this->userService->getUserStats();
            View::json(['success' => true, 'stats' => $stats]);
        } catch (Exception $e) {
            View::json([
                'success' => false,
                'error' => DEBUG_MODE ? $e->getMessage() : 'Failed to load statistics'
            ], 500);
        }
    }
}
