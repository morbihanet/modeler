<?php
namespace Morbihanet\Modeler;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class ApiController extends BaseController
{
    use ApiResponse;

    protected ?string $resource = null;

    public function __construct(Request $request)
    {
        $this->resource = $this->getResourceName($request);
    }

    public function index(Request $request)
    {
        return $this->showAll(datum($this->resource)::cursor());
    }

    public function store(Request $request)
    {
        $item = datum($this->resource)::create($request->all());

        return $this->showOne($item);
    }

    public function show(Request $request)
    {
        if ($item = datum($this->resource)::find($request->get('id'))) {
            return $this->showOne($item);
        }

        return $this->errorResponse('Resource not found', 404);
    }

    public function update(Request $request)
    {
        if ($item = datum($this->resource)::find($request->get('id'))) {
            return $this->showOne($item->update($request->all()));
        }

        return $this->errorResponse('Resource not found', 404);
    }

    public function destroy(Request $request)
    {
        if ($item = datum($this->resource)::find($id = $request->get('id'))) {
            $item->delete();

            return $this->successResponse(['message' => "Resource '$id' deleted"]);
        }

        return $this->errorResponse('Resource not found', 404);
    }
}