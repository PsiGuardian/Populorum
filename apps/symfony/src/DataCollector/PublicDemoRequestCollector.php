<?php
declare(strict_types=1);
namespace App\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpFoundation\{Request,Response};
/** Le profiler public montre les diagnostics utiles, sans cookies, clés ou jetons. */
final class PublicDemoRequestCollector extends RequestDataCollector{
 public function collect(Request $request,Response $response,?\Throwable $exception=null):void{
  parent::collect($request,$response,$exception);
  foreach(['request_request','request_files','request_cookies','response_cookies','session_metadata','session_attributes','session_usages','flashes','dotenv_vars'] as $k)$this->data[$k]=[];
  $this->data['request_server']=array_intersect_key($this->data['request_server'],array_flip(['REQUEST_METHOD','SERVER_PROTOCOL','SERVER_SOFTWARE','HTTPS']));
  $this->data['request_headers']=array_intersect_key($this->data['request_headers'],array_flip(['accept','accept-language','content-type','host','user-agent']));
  unset($this->data['response_headers']['set-cookie']);$this->data['content']='[Corps omis sur le profiler public de démonstration]';$this->data['curlCommand']='curl '.escapeshellarg($request->getSchemeAndHttpHost().$request->getPathInfo());
  $this->data['request_query']=array_intersect_key($this->data['request_query'],array_flip(['view','territory','id','locale','panel']));
 }
}
