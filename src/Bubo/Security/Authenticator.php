<?php

namespace Bubo\Security;

use Nette;
use Nette\Security as NS;

use Bubo\Database\Connection;
use DibiConnection;

/**
 * Default implementation of Bubo authenticator
 */
class Authenticator extends Nette\Object implements NS\IAuthenticator
{

    /**
     * Dibi connection
     * @var DibiConnection
     */
    public $connection;

    /**
     * Salt
     * @var string
     */
    private $salt = 'kUdv5p*&';

    /**
     * Constructor
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Returns salt
     * @return string
     */
    public function getSalt()
    {
        return $this->salt;
    }

    /**
     * Authenticate the user against DB table
     * @param array $credentials
     * @return \Nette\Security\Identity
     * @throws NS\AuthenticationException
     */
    public function authenticate(array $credentials)
    {
        list($login, $password) = $credentials;

        $user = $this->connection->fetch("SELECT * FROM [:core:users] WHERE [login] = %s", $login);

        if (!$user) {
            throw new NS\AuthenticationException('Uživatel nenalezen.');
        }

        if ($user->password !== sha1($this->salt.$password)) {
            throw new NS\AuthenticationException('Chybné heslo.');
        }

        // get acl
        $acl = $user->acl;
        if (empty($acl)) {
            // get acl from role template
            $acl = $this->connection->fetchSingle('SELECT * FROM [:core:roles] WHERE [role] = %s', $user->role);
            if (empty($acl)) {
                throw new NS\AuthenticationException('Neznámá role');
            }
        }

        $data = array(
            'login'     => $user->login,
            'userData'  => $user,
        );

        return new NS\Identity($user->user_id, $user->role, $data);
    }

}