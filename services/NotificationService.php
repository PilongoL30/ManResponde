<?php
/**
 * Notification Service
 * Handles all notification-related business logic
 */

class NotificationService {
    /**
     * Send FCM notification to a user
     */
    public function sendNotification(string $fcmToken, string $title, string $body, array $data = []): bool {
        global $fcm;
        
        try {
            $message = $fcm->createMessage()
                ->withNotification(['title' => $title, 'body' => $body])
                ->withData($data);
                
            $fcm->send($message, $fcmToken);
            return true;
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("FCM notification error: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Send notification to multiple users
     */
    public function sendBulkNotification(array $fcmTokens, string $title, string $body, array $data = []): array {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($fcmTokens as $token) {
            if ($this->sendNotification($token, $title, $body, $data)) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed to send to token: " . substr($token, 0, 20) . "...";
            }
        }
        
        return $results;
    }
    
    /**
     * Send topic notification
     */
    public function sendTopicNotification(string $topic, string $title, string $body, array $data = []): bool {
        global $fcm;
        
        try {
            $message = $fcm->createMessage()
                ->withNotification(['title' => $title, 'body' => $body])
                ->withData($data);
                
            $fcm->sendToTopic($topic, $message);
            return true;
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("FCM topic notification error: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Send notification when report status changes
     */
    public function notifyReportStatusChange(string $reportId, string $category, string $oldStatus, string $newStatus, ?string $fcmToken = null): bool {
        $statusMessages = [
            'approved' => 'Your report has been approved and is now being processed.',
            'responded' => 'Responders have been dispatched to your location.',
            'declined' => 'Your report has been reviewed and declined.',
        ];
        
        $title = ucfirst($category) . " Report Update";
        $body = $statusMessages[$newStatus] ?? "Your report status has been updated to: {$newStatus}";
        
        $data = [
            'reportId' => $reportId,
            'category' => $category,
            'status' => $newStatus,
            'timestamp' => time()
        ];
        
        if ($fcmToken) {
            return $this->sendNotification($fcmToken, $title, $body, $data);
        }
        
        return false;
    }
    
    /**
     * Send notification to nearby users about an emergency
     */
    public function notifyNearbyUsers(array $location, string $emergencyType, float $radiusKm = 5.0): array {
        // This would integrate with user location tracking
        // For now, placeholder implementation
        return [
            'sent' => 0,
            'message' => 'Location-based notifications require user location tracking implementation'
        ];
    }
    
    /**
     * Get notification history from Firestore
     */
    public function getNotificationHistory(int $limit = 50, bool $useCache = true): array {
        $cacheKey = 'notification_history_' . $limit;
        
        if ($useCache) {
            $cached = cache_get($cacheKey, 60);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        try {
            $collection = 'notifications';
            $notifications = [];
            
            if (function_exists('list_latest_reports')) {
                $notifications = list_latest_reports($collection, $limit, false);
            }
            
            cache_set($cacheKey, $notifications, 60);
            return $notifications;
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("Error fetching notification history: " . $e->getMessage());
            }
            return [];
        }
    }
    
    /**
     * Log notification to Firestore
     */
    public function logNotification(string $recipientId, string $type, string $title, string $body, array $metadata = []): bool {
        try {
            $collection = 'notifications';
            $data = [
                'recipientId' => $recipientId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'metadata' => $metadata,
                'sentAt' => time(),
                'read' => false
            ];
            
            if (function_exists('firestore_add_doc')) {
                $docId = firestore_add_doc($collection, $data);
                return !empty($docId);
            }
            
            // REST fallback
            $url = firestore_base_url() . '/' . rawurlencode($collection);
            $body = ['fields' => firestore_encode_fields($data)];
            $response = firestore_rest_request('POST', $url, $body);
            
            return !empty($response);
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("Error logging notification: " . $e->getMessage());
            }
            return false;
        }
    }
}
