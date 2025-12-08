<?php
/**
 * User Service
 * Handles all user-related business logic and data operations
 */

class UserService {
    /**
     * Get all users from Firebase
     */
    public function getAllUsers(bool $useCache = true): array {
        global $firebase;
        
        $cacheKey = 'all_users';
        if ($useCache) {
            $cached = cache_get($cacheKey, 60);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        try {
            $auth = $firebase->createAuth();
            $users = $auth->listUsers($defaultMaxResults = 1000);
            
            $userList = [];
            foreach ($users as $user) {
                $userList[] = [
                    'uid' => $user->uid,
                    'email' => $user->email,
                    'displayName' => $user->displayName,
                    'disabled' => $user->disabled,
                    'emailVerified' => $user->emailVerified,
                    'metadata' => [
                        'createdAt' => $user->metadata->createdAt,
                        'lastLoginAt' => $user->metadata->lastLoginAt,
                    ],
                    'customClaims' => $user->customClaims ?? [],
                ];
            }
            
            cache_set($cacheKey, $userList, 60);
            return $userList;
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("Error fetching users: " . $e->getMessage());
            }
            return [];
        }
    }
    
    /**
     * Get user by UID
     */
    public function getUserByUid(string $uid): ?array {
        global $firebase;
        
        try {
            $auth = $firebase->createAuth();
            $user = $auth->getUser($uid);
            
            return [
                'uid' => $user->uid,
                'email' => $user->email,
                'displayName' => $user->displayName,
                'disabled' => $user->disabled,
                'emailVerified' => $user->emailVerified,
                'metadata' => [
                    'createdAt' => $user->metadata->createdAt,
                    'lastLoginAt' => $user->metadata->lastLoginAt,
                ],
                'customClaims' => $user->customClaims ?? [],
            ];
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("Error fetching user: " . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Verify/unverify user
     */
    public function setUserVerificationStatus(string $uid, bool $isVerified): bool {
        global $firebase;
        
        try {
            $auth = $firebase->createAuth();
            $auth->setCustomUserClaims($uid, ['isVerified' => $isVerified]);
            
            // Clear cache
            cache_delete('all_users');
            cache_delete('pending_users_count');
            
            return true;
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("Error setting user verification: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Get pending (unverified) users count
     */
    public function getPendingUsersCount(bool $useCache = true): int {
        $cacheKey = 'pending_users_count';
        
        if ($useCache) {
            $cached = cache_get($cacheKey, 30);
            if ($cached !== null) {
                return (int)$cached;
            }
        }
        
        $users = $this->getAllUsers($useCache);
        $count = 0;
        
        foreach ($users as $user) {
            $isVerified = $user['customClaims']['isVerified'] ?? false;
            if (!$isVerified) {
                $count++;
            }
        }
        
        cache_set($cacheKey, $count, 30);
        return $count;
    }
    
    /**
     * Get pending users list
     */
    public function getPendingUsers(bool $useCache = true): array {
        $users = $this->getAllUsers($useCache);
        $pending = [];
        
        foreach ($users as $user) {
            $isVerified = $user['customClaims']['isVerified'] ?? false;
            if (!$isVerified) {
                $pending[] = $user;
            }
        }
        
        return $pending;
    }
    
    /**
     * Get verified users list
     */
    public function getVerifiedUsers(bool $useCache = true): array {
        $users = $this->getAllUsers($useCache);
        $verified = [];
        
        foreach ($users as $user) {
            $isVerified = $user['customClaims']['isVerified'] ?? false;
            if ($isVerified) {
                $verified[] = $user;
            }
        }
        
        return $verified;
    }
    
    /**
     * Update user profile
     */
    public function updateUserProfile(string $uid, array $data): bool {
        global $firebase;
        
        try {
            $auth = $firebase->createAuth();
            $properties = [];
            
            if (isset($data['displayName'])) {
                $properties['displayName'] = $data['displayName'];
            }
            if (isset($data['email'])) {
                $properties['email'] = $data['email'];
            }
            if (isset($data['password'])) {
                $properties['password'] = $data['password'];
            }
            
            $auth->updateUser($uid, $properties);
            
            // Clear cache
            cache_delete('all_users');
            
            return true;
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("Error updating user: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Delete user
     */
    public function deleteUser(string $uid): bool {
        global $firebase;
        
        try {
            $auth = $firebase->createAuth();
            $auth->deleteUser($uid);
            
            // Clear cache
            cache_delete('all_users');
            cache_delete('pending_users_count');
            
            return true;
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("Error deleting user: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Get user statistics
     */
    public function getUserStats(bool $forceRefresh = false): array {
        $cacheKey = 'user_stats';
        
        if (!$forceRefresh) {
            $cached = cache_get($cacheKey, 120);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $users = $this->getAllUsers(!$forceRefresh);
        
        $stats = [
            'total' => count($users),
            'verified' => 0,
            'pending' => 0,
            'disabled' => 0,
            'emailVerified' => 0,
        ];
        
        foreach ($users as $user) {
            if ($user['customClaims']['isVerified'] ?? false) {
                $stats['verified']++;
            } else {
                $stats['pending']++;
            }
            
            if ($user['disabled']) {
                $stats['disabled']++;
            }
            
            if ($user['emailVerified']) {
                $stats['emailVerified']++;
            }
        }
        
        cache_set($cacheKey, $stats, 120);
        return $stats;
    }
}
