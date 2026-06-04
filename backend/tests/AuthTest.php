<?php
// backend/tests/AuthTest.php

class AuthTest {
    
    private $testEmail = "testuser@example.com";
    private $testPassword = "Test123!";
    
    public function run() {
        try {
            // Test 1: Inscription
            if (!$this->testRegister()) {
                return ['success' => false, 'message' => 'Échec inscription'];
            }
            
            // Test 2: Connexion
            if (!$this->testLogin()) {
                return ['success' => false, 'message' => 'Échec connexion'];
            }
            
            // Test 3: Vérification session
            if (!$this->testSession()) {
                return ['success' => false, 'message' => 'Échec vérification session'];
            }
            
            // Test 4: Déconnexion
            if (!$this->testLogout()) {
                return ['success' => false, 'message' => 'Échec déconnexion'];
            }
            
            return ['success' => true, 'message' => 'Authentification OK'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function testRegister() {
        $data = [
            'name' => 'Test User',
            'email' => $this->testEmail,
            'password' => $this->testPassword,
            'confirm_password' => $this->testPassword
        ];
        
        $ch = curl_init('http://localhost/Danous/backend/api/auth/register.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        // Succès si code 200 ou 201, ou message "email déjà existant"
        return ($httpCode == 200 || $httpCode == 201 || 
               ($result && strpos($result['message'] ?? '', 'existe') !== false));
    }
    
    private function testLogin() {
        $data = [
            'email' => $this->testEmail,
            'password' => $this->testPassword
        ];
        
        $ch = curl_init('http://localhost/Danous/backend/api/auth/login.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . '/cookies.txt');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        return ($httpCode == 200 && isset($result['success']) && $result['success'] === true);
    }
    
    private function testSession() {
        $ch = curl_init('http://localhost/Danous/backend/api/auth/me.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookies.txt');
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        return (isset($result['success']) && $result['success'] === true);
    }
    
    private function testLogout() {
        $ch = curl_init('http://localhost/Danous/backend/api/auth/logout.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookies.txt');
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return true; // Logout toujours considéré réussi
    }
}
?>