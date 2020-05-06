<?php
namespace Morbihanet\Modeler;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CrudController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected array $options = [];
    protected ?string $model = null;

    public function __construct()
    {
        $this->setup();
    }

    protected function setup() {}

    protected function getModel(): ?Model
    {
        if (!empty($this->model)) {
            return make_with($this->model);
        }

        return null;
    }

    public function index()
    {
        $model = $this->getModel();
        $items = $model::cursor();
        $options = $this->options;

        return view('crud.index', compact('items', 'model', 'options'));
    }

    public function create()
    {
        $model = $this->getModel();
        $item = $model->newModel();
        $options = $this->options;

        return view('crud.create', compact('item', 'model', 'options'));
    }

    public function store()
    {
        $data = $this->getModel()::validate();
    }

    public function show($id)
    {
        $model = $this->getModel();
        $item = $model::find($id);

        if (!empty($item)) {
            $model = $this->getModel();
            $options = $this->options;

            return view('crud.show', compact('item', 'model', 'options'));
        }

        return redirect()->back();
    }

    public function edit(int $id)
    {
        $model = $this->getModel();
        $item = $model::find($id);

        if (!empty($item)) {
            $model = $this->getModel();
            $options = $this->options;

            return view('crud.edit', compact('item', 'model', 'options'));
        }

        return redirect()->back();
    }

    public function update(int $id)
    {
        $model = $this->getModel();
        $item = $model::find($id);

        if (!empty($item)) {
            $data = $this->getModel()::validate();
        }

        return redirect()->back();
    }

    public function destroy(int $id)
    {
        $model = $this->getModel();
        $item = $model::find($id);

        if (!empty($item)) {
            $item->delete();
        }

        return redirect()->back();
    }
}