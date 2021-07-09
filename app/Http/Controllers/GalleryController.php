<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Gallery;

/**
 * @group Gallery
 * Endpoint that manages event galleries.
 */
class GalleryController extends Controller
{   
    /**
     * _index_: list gallery by event.
     * @urlParam event required
     */
    public function index($event_id)
    {   
        return JsonResource::collection(
            Gallery::where('event_id', $event_id)
                ->paginate(config('app.page_size'))
        );
    }

    /**
     * _store_: create new register for gallery.
     * @authenticated
     * 
     * @urlParam event required
     * 
     * @bodyParam name string required name of image. Example: Arbol
     * @bodyParam description string  description of image. Example: Esta pintura es de un arbol.
     * @bodyParam image string required route of image. Example: https://storage.googleapis.com/eviusauth.appspot.com/evius/events/87Pxr9PYNfBEDMbX19CeTU8wwTFHpb2XB3n2bnak.jpg
     * @bodyParam price float Example: 10000   
     *  
     */
    public function store(Request $request, $event_id)
    {
        $validated = $request->validate([
            'name' => 'required',
            'image' => 'required',            
        ]);

        $data = $request->json()->all();
        $data['event_id'] =  $event_id;
        $result = new Gallery($data);
        $result->save();
        return $result;
    }

    /**
     * _show_: consult information on a specific gallery
     *
     * @urlParam event required event Example: 5bb25243b6312771e92c8693
     * @urlParam gallery required gallery 
     * 
     */
    public function show($event_id ,  $id)
    {
        $gallery = Gallery::find($id);
        $response = new JsonResource($gallery);
        return $response;
    }

    /**
     * _update_: update image of gallery specific.
     * @authenticated
     * 
     * @urlParam event required
     * 
     * @bodyParam name string name of image. Example: Arbol
     * @bodyParam description string  description of image. Example: Esta pintura es de un arbol.
     * @bodyParam image string route of image. Example: https://storage.googleapis.com/eviusauth.appspot.com/evius/events/87Pxr9PYNfBEDMbX19CeTU8wwTFHpb2XB3n2bnak.jpg
     * @bodyParam price float Example: 10000  
     */
    public function update(Request $request, $event_id, $gallery_id)
    {
        $data = $request->json()->all();
        $gallery = Gallery::find($id);
        $gallery->fill($data);
        $gallery->save();
        return $data;
    }


    /**
     * _destroy_: delete image in the gallery.
     *
     * @urlParam gallery required id of the event to be eliminated
     * 
     */
    public function destroy($event_id ,  $id)
    {
        $gallery = Gallery::findOrFail($id);
        return (string) $gallery->delete();
    }

}
