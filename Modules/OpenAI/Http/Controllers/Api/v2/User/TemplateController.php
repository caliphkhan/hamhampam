<?php
/**
 * @author TechVillage <support@techvill.org>
 *
 * @contributor Kabir Ahmed <[kabir.techvill@gmail.com]>
 *
 * @created 26-01-2024
 */

namespace Modules\OpenAI\Http\Controllers\Api\v2\User;


use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Modules\OpenAI\Entities\Archive;
use Modules\OpenAI\Services\v2\ArchiveService;
use Modules\OpenAI\Services\v2\TemplateService;
use Modules\OpenAI\Http\Requests\v2\TemplateRequest;
use Modules\OpenAI\Http\Resources\v2\TemplateResource;


class TemplateController extends Controller
{
    /**
     * The instance of the code service.
     *
     * @var TemplateService
     */
    protected $templateService;

    /**
     * Constructor method.
     *
     * Instantiates the class and sets up the vector service.
     *
     * @param  TemplateService  $templateService
     * 
     * @return void
     */
    public function __construct(TemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Returns a paginated list of template resources.
     * 
     * @param Request $request
     * 
     * @return array
     */
    public function index(Request $request): array
    {
        $templates = (new Archive())->templates()->paginate(preference('row_per_page'));
        return TemplateResource::collection($templates)->response()->getData(true);
    }

    /**
     * Generate template data based on the provided request.
     *
     *
     * @param  TemplateRequest  $request The request containing the template data.
     * @return JsonResponse The response containing the status, success flag, message, and template ID.
     */

    public function generate(TemplateRequest $request): JsonResponse
    {
        $checkSubscription = checkUserSubscription(auth()->user()->id, 'word');

        if ($checkSubscription['status'] != 'success') {
            return response()->json(['error' => $checkSubscription['response']], Response::HTTP_FORBIDDEN);
        }

        try {
            \DB::beginTransaction();
            $this->templateService->initiate($request->validated());
            $id = $this->templateService->prepareData();
            if ($id) {
                \DB::commit();
                return response()->json(['data' => [
                    'templateId' => $id 
                ]], Response::HTTP_OK);
            }

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Process the streaming template data.
     *
     * This method initiates the template service with the request data, retrieves template data, and processes it.
     *
     * @return mixed The processed template data.
     */
    public function process()
    {
        $this->templateService->initiate(request()->all());
        $this->templateService->templateData();
        return $this->templateService->processData();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $chatbot The ID of the chatbot to display.
     * @return JsonResponse|TemplateResource
     */
    public function show($id): JsonResponse|TemplateResource
    {
        if (!is_numeric($id)) {
            return response()->json(['error' => __('Invalid Request.')], Response::HTTP_FORBIDDEN);
        }
    
        $history = Archive::where('id', $id)->whereType('template')->first();
    
        if (!$history) {
            return response()->json(['error' => __('No data found')], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => new TemplateResource($history)], Response::HTTP_OK);
    }

    /**
     * Delete a code chat reply by its ID.
     *
     * @param int $id The ID of the code chat reply to delete.
     * @return JsonResponse|null
     *         The JSON response indicating the success or failure of the deletion operation.
     */
    public function delete($id): JsonResponse|null
    {
        if (!is_numeric($id)) {
            return response()->json(['error' => __('Invalid Request.')], Response::HTTP_FORBIDDEN);
        }

        try {
            ArchiveService::delete($id, 'template');
            return response()->json(['message' => __('The :x has been successfully deleted.', ['x' => __('document')])], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode());
        }
    }

}
