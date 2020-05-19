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

    public function index()
    {
        return $this->showAll(datum($this->resource)::all());
    }

    public function store(Request $request)
    {
        $item = datum($this->resource)::create($request->all());

        return $this->showOne($item);
    }

    public function show($id)
    {
        if ($item = datum($this->resource)::find((int) $id)) {
            return $this->showOne($item);
        }

        return $this->errorResponse('Resource not found', 404);
    }

    public function update(Request $request, $id)
    {
        if ($item = datum($this->resource)::find((int) $id)) {
            return $this->showOne($item->update($request->all()));
        }

        return $this->errorResponse('Resource not found', 404);
    }

    public function destroy($id)
    {
        if ($item = datum($this->resource)::find((int) $id)) {
            $item->delete();

            return $this->successResponse(['message' => "Resource '$id' deleted"]);
        }

        return $this->errorResponse('Resource not found', 404);
    }
}