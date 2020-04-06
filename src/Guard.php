<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isAuth()
 * @method static bool isGuest()
 * @method static Item|null user()
 * @method static Item|null login(string $username, string $password)
 * @method static void logout()
 * @method static bool is(string ...$roles)
 * @method static bool authorize(string $policy, Item $item)
 * @method static mixed|Session getSession()
 * @method static Db getDb()
 * @method static Auth setSession(&$session)
 * @method static Auth setDb(IteractorDb $db)
 *
 * @see \Morbihanet\Modeler\Auth
 */
class Guard extends Facade
{
    /**
     * @return Auth
     */
    protected static function getFacadeAccessor()
    {
        $session = Core::session();
        $model = $session->getUserModel();

        $auth = Auth::getInstance($session, new $model());

        Core::set('guard', $auth);

        return $auth;
    }

    /**
     * @return Auth
     */
    public static function self()
    {
        return static::getFacadeAccessor();
    }
}
