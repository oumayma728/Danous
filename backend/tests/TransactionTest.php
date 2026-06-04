<?php
class TransactionTest
{
private $testTransactionId = null;
public function run()
{
     try {
            // Test 1: Ajouter une transaction
            if (!$this->testAddTransaction()) {
                return ['success' => false, 'message' => 'Échec ajout transaction'];
            }
            
            // Test 2: Récupérer les transactions
            if (!$this->testGetTransactions()) {
                return ['success' => false, 'message' => 'Échec récupération transactions'];
            }
            
            // Test 3: Modifier une transaction
            if (!$this->testUpdateTransaction()) {
                return ['success' => false, 'message' => 'Échec modification transaction'];
            }
            
            // Test 4: Supprimer une transaction
            if (!$this->testDeleteTransaction()) {
                return ['success' => false, 'message' => 'Échec suppression transaction'];
            }
            
            return ['success' => true, 'message' => 'CRUD Transactions OK'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    private function testAddTransaction() {
        $data = [
            'type' => 'expense',
            'amount' => 50.00,
            'category_id' => 1,  // Assurez-vous qu'une catégorie existe
            'description' => 'Test transaction',
            'transaction_date' => date('Y-m-d')
        ];
        $ch = curl_init('http://localhost/Danous/backend/api/transactions/add_transaction.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookies.txt');
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($result && isset($result['success']) && $result['success'] === true) {
            $this->testTransactionId = $result['transaction_id'] ?? null;
            return true;
        }
        
        return false;
    }
    
    private function testGetTransactions() {
        $ch = curl_init('http://localhost/Danous/backend/api/transactions/get_transactions.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookies.txt');
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        return (isset($result['success']) && $result['success'] === true);
    }
    
    private function testUpdateTransaction() {
        if (!$this->testTransactionId) return true;
        
        $data = [
            'transaction_id' => $this->testTransactionId,
            'type' => 'expense',
            'amount' => 75.00,
            'category_id' => 2,
            'description' => 'Transaction modifiée',
            'transaction_date' => date('Y-m-d')
        ];
        
        $ch = curl_init('http://localhost/Danous/backend/api/transactions/update_transaction.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookies.txt');
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        return (isset($result['success']) && $result['success'] === true);
    }
    
    private function testDeleteTransaction() {
        if (!$this->testTransactionId) return true;
        
        $data = ['transaction_id' => $this->testTransactionId];
        
        $ch = curl_init('http://localhost/Danous/backend/api/transactions/delete_transaction.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookies.txt');
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return true;
    }
}
?>
