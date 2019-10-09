<?php

namespace App\Http\Controllers;

use App\Models\Attendee;
use App\Certificate;
use App\Event;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;
use Storage;
use PDF;
use Mail;

/**
 * @resource Event
 *
 *
 */
class CertificateController extends Controller
{

    /* por defecto el modelo es en singular y el nombre de la tabla en prural
    //protected $table = 'categories';
    $a = new Certificate();
    var_dump($a->getTable());
     */

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return JsonResource::collection(
            Certificate::paginate(config('app.page_size'))
        );
        //$events = Event::where('visibility', $request->input('name'))->get();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        
    }
   /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->json()->all();
        $result = new Certificate($data);
        $result->save();
        return $result;
        
    }
    public function delete($id)
    {
        $res = $id->delete();
        if ($res == true) {
            return 'True';
        } else {
            return 'Error';
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    /**
     * Display the specified resource.
     *
     * @param  \App\Certificate  $Certificate
     * @return \Illuminate\Http\Response
     */
    public function show(String $id)
    {
        $Certificate = Certificate::findOrFail($id);
        $response = new JsonResource($Certificate);
        return $response;
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Certificate  $Certificate
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id)
    {
        $data = $request->json()->all();
        $Certificate = Certificate::findOrFail($id);
        $Certificate->fill($data);
        $Certificate->save();
        return $data;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Event  $event
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request,string $id)
    {  
        $Certificate = Certificate::findOrFail($id); 
        return (string)$Certificate->delete();
            
    }


    public function indexByEvent(Request $request, String $event_id)
    {
        $query = Certificate::where("event_id", $event_id);
        $results = $query->get();
        return JsonResource::collection($results);
    }

    public function certificatePdf(Request $request)
    {
        echo "hola";
        $contentqry = Attendee::findOrFail("event_id" , "5d2de182d74d5c28047d1f85")->get();
            echo var_dump($contentqry);
            die;
        $data = $request->json()->all();
        $data = $request->json()->all();
        //$content = Certificate::where("content"); 
        //$image=$request->input("image");
       // $content=$request->input("content");
       
        //$contentqry = Certificate::where("content", $id);
        //$backgroundqry = Certificate::where("background", $id)
        //$attendee = Attendee::scope()->backgrounddOrFid($attendee_id);

        //$content = '<p><br></p> <p> <h3>CERTIFICADO DE ASISTENCIA</h3> </p> </br> <p style="margin-top:-3%;" ><span style="font-weight: 400; font-size: 14pt;"> <br></span></p> <p style="">Certificamos que&nbsp;<span style="font-style: normal; font-weight: bold;" class="name">Pablo </span> , identificada con el No. de cédula<br> <span style="font-style: normal; font-weight: bold;" class="iden">[1033801141user.identificación]</span> participó con éxito en calidad de asistente&nbsp;<span style="font-style: normal; font-weight: bold;"><br class="eventName">[event.name]</span></p><br><p style="">BOGOTÁ, COLOMBIA</p> <div style="position:absolute;bottom: 420px;left:-1440px"><span style="font-style: normal; font-weight: bold;">DOMINICA MARTÍNEZ</span><p>presidente Congreso Internacional de<br>Gerencia de Proyectos</p></div> <div style=" position:absolute;bottom:490px;right:-1540px;"><span style="font-style:normal;font-weight: bold">CLAUDIA TRUJILLO</span><p>presidente PMI - 2019</p></div>';

        //$data = [
        //    'content'   => $content,
        //    'image'     => "ASDASD"
        //];
    
        //if(($cedula)){echo "cedula no encontrada";} 
        //echo $contentqry;
        //echo gettype($contentqry);
        
        if ($request->get('download') == '1') {

            
            $pdf = PDF::loadview('Public.ViewEvent.Partials.certificate', $data);
    
            $pdf->setPaper(
                'letter',  'landscape'
            );
           /* $cedula = $data["content"];
            $nombreEvento = $data["content"];
            if(strpos($cedula, 'class="iden"')){
            $cedula = $data["content"];
            $cedula = strstr($cedula,'"iden">');
            $cedula = strstr($cedula,'</span>',true) ;
            $cedula = (string) filter_var($cedula, FILTER_SANITIZE_NUMBER_INT);
            $contentqry = Attendee::where('identificacion', $cedula)->where("event_id" , "5d2de182d74d5c28047d1f85")->get();
            $cedula = json_decode(json_encode($contentqry));
            $cedula = $cedula[0]->email;
            $nombreEvento = strstr($nombreEvento,'"eventName">');
            $nombreEvento = strstr($nombreEvento,'>');
            $nombreEvento = substr($nombreEvento,1);
            $nombreEvento = strstr($nombreEvento,'</span>',true) ;
     
            //FUNCION DE ENVIAR CORREO
            
            Mail::send('Public.ViewEvent.Partials.certificate', $data, function ($message) use ($data,$pdf,$cedula,$nombreEvento){
                $message->to($cedula,"Evento PMI")
                ->subject("Tus certificados para el evento",$nombreEvento)
                ->attachData($pdf->download(),'Tickets.pdf');
                });
            }*/
            
            return $pdf->download('Tickets.pdf');
        }
        return view(
            'Public.ViewEvent.Partials.certificate',$data);
    }
        //return view('Public.ViewEvent.Partials.PDFTicket', $data);    
    

    public function generateCertificate(Request $request)
    {
        $data = $request->json()->all();
        //
       
        /*$content = '<p><br></p> <p> <h3>CERTIFICADO DE ASISTENCIA</h3> </p> </br> <p style="margin-top:-3%;" ><span style="font-weight: 400; font-size: 14pt;"> <br></span></p> <p style="color:#5E605E">Certificamos que&nbsp;<span style="font-style: normal; font-weight: bold;" class="name">Pablo </span> , identificada con el No. de cédula<br> <span style="font-style: normal; font-weight: bold;" class="iden">[1033801141user.identificación]</span> participó con éxito en calidad de asistente&nbsp;<span style="font-style: normal; font-weight: bold;"><br class="eventName">[event.name]</span></p><br><p style="color:#5E605E">BOGOTÁ, COLOMBIA</p> <div style="position:absolute;bottom: 420px;left:-1440px"><span style="font-style: normal; font-weight: bold;">DOMINICA MARTÍNEZ</span><p>presidente Congreso Internacional de<br>Gerencia de Proyectos</p></div> <div style=" position:absolute;bottom:490px;right:-1540px;"><span style="font-style:normal;font-weight: bold">CLAUDIA TRUJILLO</span><p>presidente PMI - 2019</p></div>';
      
        $data = [
            'content'   => $content,
            'image'     => "ASDASD"
        ];*/
            
            if ($request->get('download') == '1') {
                
                $evento = $data["content"];
               
                if(strpos($evento, 'class="iden"') ){
                    $contentqry = Attendee::where("event_id","5d9de5e3d74d5c0594053262")->get();
                    //$contentqry = Attendee::where("event_id","5d2de182d74d5c28047d1f85")->get();
                    foreach($contentqry as $datos){
                        break;
                        $data_single = json_decode(json_encode($datos,true));
                        $data['content'] = '<br><br><br><br><br><br><br><br><br><p><br></p> <p> </p> <h3 style="font-size:2.5em;font-style: italic;">El PMI Bogotá Colombia Chapter</h3> <span style="font-style: italic;font-size:1.6em">Certifica que</span> <br> <span style="font-style: italic; font-weight: bold;font-size:2em;color:black;">'.$data_single->{"nombres"}.'</span> <br> <div style="position:fixed;display:none" class="iden">'.$data_single->{"identificacion"}.'</div><br><span style="font-style: italic;font-size:1.6em">participó en calidad de</span><br><span style="font-style: italic;font-weight: bold;font-size:1.9em;color:black">Asistente</span> <br><br><span style="font-style: italic;font-size:1.2em" class="eventName">al VIII Congreso Internacional de Gerencia de Proyectos que se realizó<br>los días 27 y 28 de septiembre de 2019 en la ciudad de Bogotá, Colombia</span></p> <div style="position:absolute;bottom:20%;right:200px;color:#5E605E;font-style: italic;">Se certifica asistencia de<br>12 horas de Congreso</div>';
                        $pdf = PDF::loadview('Public.ViewEvent.Partials.certificate', $data);
                        $pdf->setPaper( 'letter',  'landscape' );
                        Mail::raw("Mediante este link puedes encontrar las presentaciones compartidas por los conferencistas, link bit.ly/memoriaspmi2019 ." , function ($message) use ($data,$pdf,$data_single){
                            $message->to($data_single->{'email'},"Evento PMI")
                            ->subject("Tus certificados para ","el VIII Congreso Internacional de Gerencia de Proyectos Bogotá 2019")
                            ->attachData($pdf->download(),'Tickets.pdf');
                            }); 
                       
                    }    
                }else if(strpos($evento, 'class="mental"') ){
                    $contentqry = Attendee::where("event_id","5d9de5e3d74d5c0594053262")->get();
                    foreach($contentqry as $datos){
                        break;
                        $data_single = json_decode(json_encode($datos,true));
                        $data['content'] = '<br><br><br><br><br><br><br><br><br><p><br></p> <p> </p> <h3 style="font-size:2.5em;font-style: italic;">El PMI Bogotá Colombia Chapter</h3> <span style="font-style: italic;font-size:1.6em">Certifica que</span> <br> <span style="font-style: italic; font-weight: bold;font-size:2em;color:black;">'.$data_single->{"nombres"}.'</span> <br> <div style="position:fixed;display:none" class="mental">'.$data_single->{"identificacion"}.'</div><br> <span style="font-style: italic;font-size:1.6em">participó en calidad de</span> <br> <span style="font-style: italic;font-weight: bold;font-size:1.9em;color:black">Asistente</span> <br><br> <span style="font-style: italic;font-size:1.2em" class="eventName">en la jornada de taller <strong>La mentalidad ágil como una fortaleza de resultado y generación de valor</strong><br>y en el VIII Congreso Internacional de Gerencia de Proyectos que se realizaron<br>los días 26, 27 y 28 de septiembre de 2019 en la ciudad de Bogotá, Colombia</span></p> <div style="position:absolute;bottom:20%;right:200px;color:#5E605E;font-style: italic;">Se certifica asistencia de<br> 4 horas de Taller<br> 12 horas de Congreso</div>';
                        $pdf = PDF::loadview('Public.ViewEvent.Partials.certificate', $data);
                        $pdf->setPaper( 'letter',  'landscape' );
                        Mail::raw("Mediante este link puedes encontrar las presentaciones compartidas por los conferencistas, link bit.ly/memoriaspmi2019 ." , function ($message) use ($data,$pdf,$data_single){
                            $message->to($data_single->{'email'},"Evento PMI")
                            ->subject("Tus certificados para ","el VIII Congreso Internacional de Gerencia de Proyectos Bogotá 2019")
                            ->attachData($pdf->download(),'Tickets.pdf');
                        });  
                        return $pdf->download('Tickets.pdf');
                           
                    }
                }else if(strpos($evento, 'class="desing"') ){

                    $contentqry = Attendee::where("event_id","5d9de5e3d74d5c0594053262")->get();
                    foreach($contentqry as $datos){
                        break;
                        $data_single = json_decode(json_encode($datos,true));
                        $data['content'] = '<br><br><br><br><br><br><br><br><br><p><br></p> <p> </p> <h3 style="font-size:2.5em;font-style: italic;">El PMI Bogotá Colombia Chapter</h3> <span style="font-style: italic;font-size:1.6em">Certifica que</span> <br> <span style="font-style: italic; font-weight: bold;font-size:2em;color:black;">'.$data_single->{"nombres"}.'</span> <br> <div style="position:fixed;display:none" class="desing">'.$data_single->{"identificacion"}.'</div><br> <span style="font-style: italic;font-size:1.6em">participó en calidad de</span> <br> <span style="font-style: italic;font-weight: bold;font-size:1.9em;color:black">Asistente</span> <br><br> <span style="font-style: italic;font-size:1.2em" class="eventName">en la jornada de taller <strong>¿Es posible pensar diferente? Una experiencia real de Design Thinking</strong><br>y en el VIII Congreso Internacional de Gerencia de Proyectos que se realizaron<br>los días 26, 27 y 28 de septiembre de 2019 en la ciudad de Bogotá, Colombia</span></p> <div style="position:absolute;bottom:20%;right:200px;color:#5E605E;font-style: italic;">Se certifica asistencia de<br> 4 horas de Taller<br> 12 horas de Congreso</div>';
                        $pdf = PDF::loadview('Public.ViewEvent.Partials.certificate', $data);
                        $pdf->setPaper( 'letter',  'landscape' );
                        Mail::raw("Mediante este link puedes encontrar las presentaciones compartidas por los conferencistas, link http://bit.ly/2p5RmGB ." , function ($message) use ($data,$pdf,$data_single){
                            $message->to($data_single->{'email'},"Evento PMI")
                            ->subject("Tus certificados para ","el VIII Congreso Internacional de Gerencia de Proyectos Bogotá 2019")
                            ->attachData($pdf->download(),'Tickets.pdf');
                        });  
                        return $pdf->download('Tickets.pdf');
                          
                    }   
                }else if(strpos($evento, 'class="coach"') ){

                    $contentqry = Attendee::where("event_id","5d9de5e3d74d5c0594053262")->get();
                    foreach($contentqry as $datos){
                        break;
                        $data_single = json_decode(json_encode($datos,true));
                        $data['content'] = '<br><br><br><br><br><br><br><br><br><p><br></p> <p> </p> <h3 style="font-size:2.5em;font-style: italic;">El PMI Bogotá Colombia Chapter</h3> <span style="font-style: italic;font-size:1.6em">Certifica que</span> <br> <span style="font-style: italic; font-weight: bold;font-size:2em;color:black;">'.$data_single->{"nombres"}.'</span><br><div style="position:fixed;display:none" class="coach">'.$data_single->{"identificacion"}.'</div><br> <span style="font-style: italic;font-size:1.6em">participó en calidad de</span> <br> <span style="font-style: italic;font-weight: bold;font-size:1.9em;color:black">Asistente</span> <br><br> <span style="font-style: italic;font-size:1.2em" class="eventName">en la jornada de taller <strong>El Gerente Coach en acción</strong><br>y en el VIII Congreso Internacional de Gerencia de Proyectos que se realizaron<br>los días 26, 27 y 28 de septiembre de 2019 en la ciudad de Bogotá, Colombia</span></p> <div style="position:absolute;bottom:20%;right:200px;color:#5E605E;font-style: italic;">Se certifica asistencia de<br> 4 horas de Taller<br> 12 horas de Congreso</div>';
                        $pdf = PDF::loadview('Public.ViewEvent.Partials.certificate', $data);
                        $pdf->setPaper( 'letter',  'landscape' );
                        Mail::raw("Mediante este link puedes encontrar las presentaciones compartidas por los conferencistas, link http://bit.ly/2p5RmGB ." , function ($message) use ($data,$pdf,$data_single){
                            $message->to($data_single->{'email'},"Evento PMI")
                            ->subject("Tus certificados para ","el VIII Congreso Internacional de Gerencia de Proyectos Bogotá 2019")
                            ->attachData($pdf->download(),'Tickets.pdf');
                            });  
                        return $pdf->download('Tickets.pdf');
                         
                    }
                
                }    
                $pdf = PDF::loadview('Public.ViewEvent.Partials.certificate', $data);
                $pdf->setPaper( 'letter',  'landscape' );
                return $pdf->download('Tickets.pdf');
        }
        return view('Public.ViewEvent.Partials.PDFTicket', $data);
    }
}


     
