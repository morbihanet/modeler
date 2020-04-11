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
     * @var Modeler
     */
    protected $db;

    protected static $instances = [];
    protected $old_user = null;

    /**
     * @param $session
     * @param Modeler $db
     */
    public function __construct(&$session, Modeler $db)
    {
        $this->session = &$session;
        $this->db = $db;
    }

    public function __destruct()
    {
        if (null !== $this->old_user) {
            $this->loginWithId($this->old_user);
        }
    }

    /**
     * @param Item $user
     * @return bool
     */
    public function forUser(Item $user)
    {
        if ($olduser = $this->user()) {
            $this->old_user = $olduser->getId();
        }

        return $this->loginWithId($user->id) !== null;
    }

    /**
     * @param $session
     * @param Modeler $db
     * @return Auth
     */
    public static function getInstance(&$session, Modeler $db): self
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

    public function authorize(string $policy, Item $item)
    {
        if ($this->isAuth()) {
            /** @var Modeler $modeler */
            $modeler = Core::get('modeler');
            $policies = $modeler::policies();

            if (!empty($policies)) {
                $thisPolicy = Arr::get($policies, $policy);

                if (is_callable($thisPolicy)) {
                    return $thisPolicy($this->user(), $item);
                }
            }
        }

        return false;
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

        return $this->db::findOrFail($id);
    }

    /**
     * @param string $username
     * @param string $password
     * @return Item|null
     */
    public function login(
        string $username,
        string $password,
        string $usernamefield = 'email',
        string $passwordField = 'password'
    ): ?Item {
        if ($user = $this->db::where($usernamefield, $username)->first()) {
            if (password_verify($password, $user->{$passwordField})) {
                $this->session['auth'] = $user->getId();

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
     * @param int $id
     * @return Item|null
     */
    public function loginWithId(int $id): ?Item
    {
        $user = $this->db::find($id);

        if ($user) {
            $this->session['auth'] = $user->getId();

            return $user;
        }

        return null;
    }

    /**
     * @param string ...$roles
     * @return bool
     */
    public function is(string ...$roles): bool
    {
        if ($user = $this->user()) {
            $userRoles = $user->roles ?? [];

            if (Core::arrayable($userRoles)) {
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
     * @return Modeler
     */
    public function getDb(): Modeler
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
     * @param Modeler $db
     * @return Auth
     */
    public function setDb(Modeler $db): Auth
    {
        $this->db = $db;

        return $this;
    }
}
