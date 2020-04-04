<?php

namespace Morbihanet\Modeler;

use Illuminate\Support\Arr;

class Auth
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Db
     */
    protected $db;

    protected static $instances = [];

    /**
     * @param $session
     * @param Db $db
     */
    public function __construct(&$session, Db $db)
    {
        $this->session = &$session;
        $this->db = $db;
    }

    /**
     * @param $session
     * @param Db $db
     * @return Auth
     */
    public static function getInstance(&$session, Db $db): self
    {
        if (!$instance = Arr::get(self::$instances, $class = get_class($db))) {
            $instance = self::$instances[$class] = new self($session, $db);
        }

        return $instance;
    }

    /**
     * @return bool
     */
    public function check(): bool
    {
        return null !== $this->user();
    }

    /**
     * @return bool
     */
    public function isAuth(): bool
    {
        return null !== $this->user();
    }

    /**
     * @return bool
     */
    public function isGuest(): bool
    {
        return null === $this->user();
    }

    /**
     * @return Item|null
     */
    public function user(): ?Item
    {
        $id = $this->session['auth'] ?? null;

        if ($id === null) {
            return null;
        }

        return $this->db->findOrFail($id);
    }

    /**
     * @param string $username
     * @param string $password
     * @return Item|null
     */
    public function login(
        string $username,
        string $password,
        string $usernamefield = 'username',
        string $passwordField = 'password'
    ): ?Item {
        if ($user = $this->db->where($usernamefield, $username)->first()) {
            if (password_verify($password, $user->{$passwordField})) {
                $this->session['auth'] = $user->id;

                return $user;
            }
        }

        return null;
    }

    public function logout(): void
    {
        unset($this->session['auth']);
    }

    /**
     * @param string ...$roles
     * @return bool
     */
    public function is(string ...$roles): bool
    {
        if ($user = $this->user()) {
            $userRoles = $user->roles ?? [];

            if ($userRoles instanceof Iterator) {
                foreach ($userRoles as $userRole) {
                    foreach ($roles as $role) {
                        if ($userRole->name === $role) {
                            return true;
                        }
                    }
                }

                return false;
            } elseif (is_array($userRoles)) {
                foreach ($roles as $role) {
                    if (!in_array($role, $userRoles)) {
                        return false;
                    }
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @return mixed|Session
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * @return Db
     */
    public function getDb(): Db
    {
        return $this->db;
    }

    /**
     * @param $session
     * @return Auth
     */
    public function setSession(&$session): Auth
    {
        $this->session = &$session;

        return $this;
    }

    /**
     * @param Db $db
     * @return Auth
     */
    public function setDb(Db $db): Auth
    {
        $this->db = $db;

        return $this;
    }
}
