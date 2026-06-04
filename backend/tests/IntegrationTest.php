<?php
// backend/tests/IntegrationTest.php

class IntegrationTest {
    
    private $testCategoryId = null;
    private $testBudgetId = null;
    private $testTransactionId = null;
    
    public function run() {
        try {
            // Test 1: Transaction → Catégorie
            if (!$this->testTransactionToCategory()) {
                return ['success' => false, 'message' => 'Échec liaison transaction → catégorie'];
            }
            
            // Test 2: Transaction → Budget
            if (!$this->testTransactionToBudget()) {
                return ['success' => false, 'message' => 'Échec liaison transaction → budget'];
            }
            
            // Test 3: Budget progression après dépense
            if (!$this->testBudgetProgression()) {
                return ['success' => false, 'message' => 'Échec mise à jour progression budget'];
            }
            
            // Test 4: Alerte dépassement budget
            if (!$this->testBudgetAlert()) {
                return ['success' => false, 'message' => 'Échec alerte dépassement budget'];
            }
            
            return ['success' => true, 'message' => "Tests d'intégration OK"];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function testTransactionToCategory() {
        // Créer une catégorie de test
        $data = ['name' => 'Catégorie Test Intégration', 'budget_limit' => 100];
        
        $ch = curl_init('http://localhost/Danous/backend/api/categories/add_category.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookies.txt');
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($result && isset($result['success']) && $result['success'] === true) {
            $this->testCategoryId = $result['category_id'];
            
            // Ajouter une transaction avec cette catégorie
            $transaction = [
                'type' => 'expense',
                'amount' => 50,
                'category_id' => $this->testCategoryId,
                'description' => 'Test intégration',
                'transaction_date' => date('Y-m-d')
            ];
            
            $ch = curl_init('http://localhost/Danous/backend/api/transactions/add_transaction.php');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transaction));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookies.txt');
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            return (isset($result['success']) && $result['success'] === true);
        }
        
        return false;
    }
    
    private function testTransactionToBudget() {
        // Créer un budget
        $budget = [
            'name' => 'Budget Test Intégration',
            'period' => 'monthly',
            'cap_amount' => 200,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+1 month')),
            'is_shared' => false
        ];
        
        $ch = curl_init('http://localhost/Danous/backend/api/budgets/add_budget.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($budget));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookies.txt');
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($result && isset($result['success']) && $result['success'] === true) {
            $this->testBudgetId = $result['budget_id'];
            
            // Ajouter une transaction liée au budget
            $transaction = [
                'type' => 'expense',
                'amount' => 80,
                'category_id' => 1,
                'budget_id' => $this->testBudgetId,
                'description' => 'Transaction liée au budget',
                'transaction_date' => date('Y-m-d')
            ];
            
            $ch = curl_init('http://localhost/Danous/backend/api/transactions/add_transaction.php');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transaction));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookies.txt');
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            return (isset($result['success']) && $result['success'] === true);
        }
        
        return false;
    }
    
    private function testBudgetProgression() {
        if (!$this->testBudgetId) return true;
        
        // Vérifier la progression du budget
        $ch = curl_init("http://localhost/Danous/backend/api/budgets/get_budget_progress.php?budget_id={$this->testBudgetId}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookies.txt');
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        return (isset($result['success']) && $result['success'] === true);
    }
    
    private function testBudgetAlert() {
        if (!$this->testBudgetId) return true;
        
        // Ajouter une transaction qui dépasse le budget
        $transaction = [
            'type' => 'expense',
            'amount' => 150,  // 80 + 150 = 230 > cap (200)
            'category_id' => 1,
            'budget_id' => $this->testBudgetId,
            'description' => 'Transaction pour déclencher alerte',
            'transaction_date' => date('Y-m-d')
        ];
        
        $ch = curl_init('http://localhost/Danous/backend/api/transactions/add_transaction.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transaction));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookies.txt');
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        // Vérifier que l'alerte a été déclenchée
        return (isset($result['alert']) || isset($result['alert_message']));
    }
}
?>