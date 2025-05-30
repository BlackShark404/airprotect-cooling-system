<?php

namespace App\Models;

use PDO;

/**
 * User Model
 * 
 * This model represents a user in the system and extends the Model class
 * with specific functionality, using PostgreSQL NOW() for timestamps.
 */
class UserModel extends Model
{
    protected $table = 'user_account';
    protected $primaryKey = 'ua_id';

    protected $fillable = [
        'ua_profile_url',
        'ua_first_name',
        'ua_last_name',
        'ua_email',
        'ua_hashed_password',
        'ua_phone_number',
        'ua_role_id',
        'ua_is_active',
        'ua_remember_token',
        'ua_remember_token_expires_at',
        'ua_last_login'
    ];

    protected $createdAtColumn = 'ua_created_at';
    protected $updatedAtColumn = 'ua_updated_at';
    protected $deletedAtColumn = 'ua_deleted_at';

    protected $timestamps = true;
    protected $useSoftDeletes = true;

    public function getAllUsers()
    {
        $sql = "SELECT user_account.*, user_role.ur_name AS role_name
                FROM user_account
                INNER JOIN user_role ON user_account.ua_role_id = user_role.ur_id
                WHERE user_account.ua_deleted_at IS NULL
                ORDER BY user_account.ua_last_name, user_account.ua_first_name";
        
        return $this->query($sql);
    }

    public function getFilteredUsers($role = '', $status = '')
    {
        $sql = "SELECT user_account.*, user_role.ur_name AS role_name
                FROM user_account
                INNER JOIN user_role ON user_account.ua_role_id = user_role.ur_id
                WHERE user_account.ua_deleted_at IS NULL";
        
        $params = [];
        
        if (!empty($role)) {
            $sql .= " AND user_role.ur_name = :role";
            $params['role'] = $role;
        }
        
        if (!empty($status)) {
            $sql .= " AND user_account.ua_is_active = :is_active";
            $params['is_active'] = ($status === 'active') ? 1 : 0;
        }
        
        $sql .= " ORDER BY user_account.ua_last_name, user_account.ua_first_name";
        
        return $this->query($sql, $params);
    }

    public function findById($id)
    {
        $sql = "SELECT user_account.*, user_role.ur_name AS role_name
                FROM user_account
                INNER JOIN user_role ON user_account.ua_role_id = user_role.ur_id
                WHERE user_account.ua_id = :id";
            
        return $this->queryOne($sql, ['id' => $id]);
    }

    public function findByEmail($email)
    {
        $sql = "SELECT user_account.*, user_role.ur_name AS role_name
                FROM user_account
                INNER JOIN user_role ON user_account.ua_role_id = user_role.ur_id
                WHERE user_account.ua_email = :email";
        
        return $this->queryOne($sql, ['email' => $email]);
    }

    public function getByRole($roleName)
    {
        $sql = "SELECT user_account.*, user_role.ur_name AS role_name
                FROM user_account
                INNER JOIN user_role ON user_account.ua_role_id = user_role.ur_id
                WHERE user_role.ur_name = :role_name
                AND user_account.ua_deleted_at IS NULL";
        
        return $this->query($sql, ['role_name' => $roleName]);
    }

    public function getNewest()
    {
        $sql = "SELECT *
                FROM user_account
                WHERE ua_deleted_at IS NULL
                ORDER BY ua_created_at DESC";
        
        return $this->query($sql);
    }

    public function search($searchTerm)
    {
        $sql = "SELECT *
                FROM user_account
                WHERE (ua_first_name ILIKE :search_term
                    OR ua_last_name ILIKE :search_term
                    OR ua_email ILIKE :search_term)
                AND ua_deleted_at IS NULL
                ORDER BY ua_last_name, ua_first_name";
        
        return $this->query($sql, ['search_term' => "%$searchTerm%"]);
    }

    public function hashPassword($password)
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    public function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    public function createUser(array $data)
    {
        $expressions = [];
        if ($this->timestamps) {
            $expressions[$this->createdAtColumn] = 'NOW()';
            $expressions[$this->updatedAtColumn] = 'NOW()';
        }

        $insertData = $this->formatInsertData($data, [], $expressions);
        $sql = "INSERT INTO {$this->table} ({$insertData['columns']})
                VALUES ({$insertData['placeholders']})";
        
        $this->execute($sql, $insertData['filteredData']);
        return $this->lastInsertId();
    }

    public function updateUser($id, array $data)
    {
        $expressions = [];
        if ($this->timestamps) {
            $expressions[$this->updatedAtColumn] = 'NOW()';
        }

        $updateData = $this->formatUpdateData($data, [], $expressions);
        $sql = "UPDATE {$this->table}
                SET {$updateData['updateClause']}
                WHERE {$this->primaryKey} = :id";
        
        $params = array_merge($updateData['filteredData'], ['id' => $id]);
        return $this->execute($sql, $params);
    }

    public function deleteUser($id, $permanent = false)
    {
        if ($permanent && $this->useSoftDeletes) {
            $sql = "DELETE FROM {$this->table}
                    WHERE {$this->primaryKey} = :id";
            return $this->execute($sql, ['id' => $id]);
        }
        
        $expressions = [];
        if ($this->timestamps) {
            $expressions[$this->deletedAtColumn] = 'NOW()';
            $expressions[$this->updatedAtColumn] = 'NOW()';
        }
        
        $updateData = $this->formatUpdateData([], [], $expressions);
        $sql = "UPDATE {$this->table}
                SET {$updateData['updateClause']}
                WHERE {$this->primaryKey} = :id";
        
        return $this->execute($sql, ['id' => $id]);
    }

    public function emailExists($email)
    {
        $sql = "SELECT EXISTS (
                    SELECT 1 FROM {$this->table}
                    WHERE ua_email = :email
                )";
        
        return $this->queryScalar($sql, ['email' => $email], false);
    }

    public function getActiveUsers($days = 30)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-$days days"));
        $sql = "SELECT *
                FROM user_account
                WHERE ua_is_active = :is_active
                AND ua_last_login >= :cutoff
                AND ua_deleted_at IS NULL
                ORDER BY ua_last_login DESC";
        
        return $this->query($sql, [
            'is_active' => true,
            'cutoff' => $cutoff
        ]);
    }

    public function findByRememberToken($token)
    {
        $sql = "SELECT *
                FROM user_account
                WHERE ua_remember_token = :token
                AND ua_remember_token_expires_at > NOW()";
        
        return $this->queryOne($sql, ['token' => $token]);
    }

    public function updateLastLogin($userId)
    {
        $expressions = [
            'ua_last_login' => 'NOW()'
        ];
        if ($this->timestamps) {
            $expressions[$this->updatedAtColumn] = 'NOW()';
        }
        
        $updateData = $this->formatUpdateData([], [], $expressions);
        $sql = "UPDATE {$this->table}
                SET {$updateData['updateClause']}
                WHERE {$this->primaryKey} = :id";
        
        return $this->execute($sql, ['id' => $userId]);
    }

    public function generateRememberToken($userId, $days = 30)
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime("+$days days"));
        
        $data = [
            'ua_remember_token' => $token,
            'ua_remember_token_expires_at' => $expiresAt
        ];
        $expressions = [];
        if ($this->timestamps) {
            $expressions[$this->updatedAtColumn] = 'NOW()';
        }
        
        $updateData = $this->formatUpdateData($data, [], $expressions);
        $sql = "UPDATE {$this->table}
                SET {$updateData['updateClause']}
                WHERE {$this->primaryKey} = :id";
        
        $this->execute($sql, array_merge($updateData['filteredData'], ['id' => $userId]));
        return $token;
    }

    public function clearRememberToken($userId)
    {
        $data = [
            'ua_remember_token' => null,
            'ua_remember_token_expires_at' => null
        ];
        $expressions = [];
        if ($this->timestamps) {
            $expressions[$this->updatedAtColumn] = 'NOW()';
        }
        
        $updateData = $this->formatUpdateData($data, [], $expressions);
        $sql = "UPDATE {$this->table}
                SET {$updateData['updateClause']}
                WHERE {$this->primaryKey} = :id";
        
        return $this->execute($sql, array_merge($updateData['filteredData'], ['id' => $userId]));
    }

    public function getFullName($user)
    {
        return $user['ua_first_name'] . ' ' . $user['ua_last_name'];
    }

    public function activateUser($userId)
    {
        $data = ['ua_is_active' => true];
        $expressions = [];
        if ($this->timestamps) {
            $expressions[$this->updatedAtColumn] = 'NOW()';
        }
        
        $updateData = $this->formatUpdateData($data, [], $expressions);
        $sql = "UPDATE {$this->table}
                SET {$updateData['updateClause']}
                WHERE {$this->primaryKey} = :id";
        
        return $this->execute($sql, array_merge($updateData['filteredData'], ['id' => $userId]));
    }

    public function deactivateUser($userId)
    {
        $data = ['ua_is_active' => false];
        $expressions = [];
        if ($this->timestamps) {
            $expressions[$this->updatedAtColumn] = 'NOW()';
        }
        
        $updateData = $this->formatUpdateData($data, [], $expressions);
        $sql = "UPDATE {$this->table}
                SET {$updateData['updateClause']}
                WHERE {$this->primaryKey} = :id";
        
        return $this->execute($sql, array_merge($updateData['filteredData'], ['id' => $userId]));
    }

    public function getActiveOnly()
    {
        $sql = "SELECT *
                FROM user_account
                WHERE ua_is_active = :is_active
                AND ua_deleted_at IS NULL";
        
        return $this->query($sql, ['is_active' => true]);
    }

    public function getInactiveUsers($days = 90)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-$days days"));
        $sql = "SELECT *
                FROM user_account
                WHERE (ua_last_login IS NULL OR ua_last_login < :cutoff)
                AND ua_deleted_at IS NULL
                ORDER BY ua_last_login ASC NULLS FIRST";
        
        return $this->query($sql, ['cutoff' => $cutoff]);
    }

    public function getAdmins()
    {
        $sql = "SELECT user_account.*
                FROM user_account
                INNER JOIN user_role ON user_account.ua_role_id = user_role.ur_id
                WHERE user_role.ur_name = :user_role
                AND user_account.ua_deleted_at IS NULL";
        
        return $this->query($sql, ['user_role' => 'admin']);
    }

    public function getRegularUsers()
    {
        $sql = "SELECT user_account.*
                FROM user_account
                INNER JOIN user_role ON user_account.ua_role_id = user_role.ur_id
                WHERE user_role.ur_name = :user_role
                AND user_account.ua_deleted_at IS NULL";
        
        return $this->query($sql, ['user_role' => 'customer']);
    }

    public function changeRole($userId, $roleId)
    {
        $data = ['ua_role_id' => $roleId];
        $expressions = [];
        if ($this->timestamps) {
            $expressions[$this->updatedAtColumn] = 'NOW()';
        }
        
        $updateData = $this->formatUpdateData($data, [], $expressions);
        $sql = "UPDATE {$this->table}
                SET {$updateData['updateClause']}
                WHERE {$this->primaryKey} = :id";
        
        return $this->execute($sql, array_merge($updateData['filteredData'], ['id' => $userId]));
    }

    public function cleanupExpiredTokens()
    {
        $data = [
            'ua_remember_token' => null,
            'ua_remember_token_expires_at' => null
        ];
        $expressions = [];
        if ($this->timestamps) {
            $expressions[$this->updatedAtColumn] = 'NOW()';
        }
        
        $updateData = $this->formatUpdateData($data, [], $expressions);
        $sql = "UPDATE {$this->table}
                SET {$updateData['updateClause']}
                WHERE ua_remember_token IS NOT NULL
                AND ua_remember_token_expires_at < NOW()";
        
        return $this->execute($sql, $updateData['filteredData']);
    }

    /**
     * Get customer statistics for the user profile
     * 
     * @param int $userId The user ID
     * @return array Customer statistics
     */
    public function getCustomerStatistics($userId)
    {
        // First check if the customer exists
        $sql = "SELECT * FROM CUSTOMER WHERE CU_ACCOUNT_ID = :user_id";
        $customer = $this->queryOne($sql, ['user_id' => $userId]);
        
        if (!$customer) {
            return [
                'active_bookings' => 0,
                'pending_services' => 0,
                'completed_services' => 0,
                'product_orders' => 0
            ];
        }
        
        // Get counts from the database for active service bookings
        $activeBookingsSql = "SELECT COUNT(*) FROM SERVICE_BOOKING 
                             WHERE SB_CUSTOMER_ID = :user_id 
                             AND SB_STATUS IN ('confirmed', 'in-progress')
                             AND SB_DELETED_AT IS NULL";
        $activeBookings = (int)$this->queryScalar($activeBookingsSql, ['user_id' => $userId]);
        
        // Get counts for pending services
        $pendingServicesSql = "SELECT COUNT(*) FROM SERVICE_BOOKING 
                              WHERE SB_CUSTOMER_ID = :user_id 
                              AND SB_STATUS = 'pending'
                              AND SB_DELETED_AT IS NULL";
        $pendingServices = (int)$this->queryScalar($pendingServicesSql, ['user_id' => $userId]);
        
        // Get counts for completed services
        $completedServicesSql = "SELECT COUNT(*) FROM SERVICE_BOOKING 
                                WHERE SB_CUSTOMER_ID = :user_id 
                                AND SB_STATUS = 'completed'
                                AND SB_DELETED_AT IS NULL";
        $completedServices = (int)$this->queryScalar($completedServicesSql, ['user_id' => $userId]);
        
        // Get counts for product orders
        $productOrdersSql = "SELECT COUNT(*) FROM PRODUCT_BOOKING 
                            WHERE PB_CUSTOMER_ID = :user_id 
                            AND PB_DELETED_AT IS NULL";
        $productOrders = (int)$this->queryScalar($productOrdersSql, ['user_id' => $userId]);
        
        // Update the customer statistics in the database
        $updateData = [
            'CU_ACTIVE_BOOKINGS' => $activeBookings,
            'CU_PENDING_SERVICES' => $pendingServices,
            'CU_COMPLETED_SERVICES' => $completedServices,
            'CU_PRODUCT_ORDERS' => $productOrders,
            'CU_TOTAL_BOOKINGS' => $activeBookings + $pendingServices + $completedServices
        ];
        
        $updateSql = "UPDATE CUSTOMER 
                     SET CU_ACTIVE_BOOKINGS = :active_bookings,
                         CU_PENDING_SERVICES = :pending_services,
                         CU_COMPLETED_SERVICES = :completed_services,
                         CU_PRODUCT_ORDERS = :product_orders,
                         CU_TOTAL_BOOKINGS = :total_bookings,
                         CU_UPDATED_AT = NOW()
                     WHERE CU_ACCOUNT_ID = :user_id";
        
        $params = [
            'active_bookings' => $activeBookings,
            'pending_services' => $pendingServices,
            'completed_services' => $completedServices,
            'product_orders' => $productOrders,
            'total_bookings' => $activeBookings + $pendingServices + $completedServices,
            'user_id' => $userId
        ];
        
        $this->execute($updateSql, $params);
        
        return [
            'active_bookings' => $activeBookings,
            'pending_services' => $pendingServices,
            'completed_services' => $completedServices,
            'product_orders' => $productOrders
        ];
    }
}