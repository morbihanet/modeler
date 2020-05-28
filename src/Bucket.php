<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Bucket
{
    protected ?string $bucket = null;
    protected $session = null;
    protected $response;

    public function __construct(string $bucket, &$session = null, ?string $url = null)
    {
        $this->bucket   = $bucket;

        if (null !== $session) {
            $this->session = &$session;
        } else {
            $this->session  = Core::session('data_bucket_' . $bucket);
        }

        $url = $url ?? config('modeler.bucket');

        $this->url = rtrim($url, '/') . '/';
    }

    public function all(string $pattern)
    {
        $data = $this->session['data'] ?? [];

        if (!empty($data)) {
            return $data;
        }

        $this->call('all', ["pattern" => $pattern]);

        $tab        = json_decode($this->response, true);
        $res        = Arr::get($tab, 'message');
        $collection = [];

        if (is_array($res)) {
            if (!empty($res)) {
                foreach ($res as $key => $row) {
                    $this->_set($key, $row);
                    $collection[] = $row;
                }
            }
        }

        $this->session['data'] = $collection;

        return $collection;
    }

    public function keys(string $pattern)
    {
        $keys = $this->session['keys'] ?? [];

        if (!empty($keys)) {
            $seg = Arr::get($keys, sha1($pattern));

            if (!empty($seg)) {
                return $seg;
            }
        }

        $this->call('keys', ["pattern" => $pattern]);

        $tab        = json_decode($this->response, true);
        $res        = Arr::get($tab, 'message', []);
        $collection = [];

        if (is_array($res)) {
            if (!empty($res)) {
                foreach ($res as $row) {
                    $collection[] = $row;
                }
            }
        }

        if (empty($keys)) {
            $keys = [];
        }

        $keys[sha1($pattern)] = $collection;
        $this->session['keys'] = $keys;

        return $collection;
    }

    public function get(string $key)
    {
        $hash = sha1($key);
        $values = $this->session['values'] ?? [];

        if (!empty($values)) {
            $value = Arr::get($values, $hash);

            if (!empty($value)) {
                return $value;
            }
        }

        $this->call('get', ["key" => $key]);

        $tab    = json_decode($this->response, true);
        $value  = Arr::get($tab, 'message');

        if (empty($values)) {
            $values = [];
        }

        $values[$hash] = $value;
        $this->session['values'] = $values;

        return $value;
    }

    public function _set(string $key, $value)
    {
        $hash = sha1($key);
        $values = $this->session['values'] ?? [];

        if (empty($values)) {
            $values = [];
        }

        $values[$hash] = $value;
        $this->session['values'] = $values;

        return $this;
    }

    public function set(string $key, $value, int $expire = 0)
    {
        $this->call('set', ["key" => $key, "value" => $value, "expire" => $expire]);

        $hash   = sha1($key);
        $values = $this->session['values'] ?? [];

        if (empty($values)) {
            $values = [];
        }

        $values[$hash] = $value;
        $this->session['values'] = $values;

        return $this;
    }

    public function expire(string $key, $value, int $ttl = 3600)
    {
        return $this->set($key, $value, time() + $ttl);
    }

    public function del(string $key)
    {
        $this->call('del', ["key" => $key]);

        $hash   = sha1($key);
        $values = $this->session['values'] ?? [];

        if (empty($values)) {
            $values = [];
        }

        $values[$hash] = null;

        $this->session['values'] = $values;
        $this->session['keys'] = [];
        $this->session['data'] = null;

        return $this;
    }

    public function incr(string $key, int $by = 1)
    {
        $val = $this->get($key);

        if (!strlen($val)) {
            $val = 1;
        } else {
            $val = (int) $val;
            $val += $by;
        }

        $this->set($key, $val);

        return $val;
    }

    public function decr(string $key, int $by = 1)
    {
        return $this->incr($key, $by * -1);
    }

    private function check()
    {
        $this->call('check');
    }

    public function upload(string $file)
    {
        $tab        = explode(DIRECTORY_SEPARATOR, $file);
        $fileName   = Arr::last($tab);
        $tab        = explode('.', $fileName);
        $extension  = Str::lower(Arr::last($tab));
        $name       = Str::uuid()->toString() . '.' . $extension;
        $data       = file_get_contents($file);

        $this->call('upload', ["data" => $data, "name" => $name]);

        $tab    = json_decode($this->response, true);
        $res    = Arr::get($tab, 'message');

        return $res;
    }

    public function data(string $data, string $extension)
    {
        $this->call('upload', ["data" => $data, "name" => Str::uuid()->toString() . '.' . Str::lower($extension)]);

        $tab    = json_decode($this->response, true);
        $res    = Arr::get($tab, 'message');

        return $res;
    }

    public function backup($file)
    {
        if (file_exists($file)) {
            $tab    = explode(DIRECTORY_SEPARATOR, $file);
            $name   = date("Y_m_d_H_i_s") . '_' . Arr::last($tab);

            $this->call('upload', ["data" => file_get_contents($file), "name" => $name]);

            $tab    = json_decode($this->response, true);
            $res    = Arr::get($tab, 'message');

            return $res;
        }

        return false;
    }

    private function call($action, $params = [])
    {
        $params['bucket'] = $this->bucket;
        $params['action'] = $action;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

        $this->response = curl_exec($ch);
    }

    public function __call(string $method, array $arguments)
    {
        $this->call($method, current($arguments));

        return Arr::get(json_decode($this->response, true), 'message');
    }
}
