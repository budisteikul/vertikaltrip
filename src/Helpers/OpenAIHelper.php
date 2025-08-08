<?php
namespace budisteikul\vertikaltrip\Helpers;

class OpenAIHelper {

	public function openai($input,$prompt)
	{
		$data_json = new \stdClass();
		$data_json->model = 'gpt-5';
		
		$data_json->input = '{
  			"model": "gpt-5",
  			"input": [
    			{
      				"role": "system",
      				"content": [
        				{
          					"type": "input_text",
          					"text": "'.$prompt.'"
        				}
      				]
    			},
    			{
      				"role": "user",
      				"content": [
        				{
          					"type": "input_text",
         					 "text": "'.$input.'"
        				}
      				]
    			}
  			]
		}';
		
		$data = json_decode($this->POST('/v1/responses',$data_json));
		
		return $data->output[0]->content[0]->text;
	}

	private function POST($url,$data,$headers=NULL){
        return $this->curl('POST',$url,$data,$headers);
    }

    private function GET($url){
        return $this->curl('GET',$url);
    }
    
    private function DELETE($url){
        return $this->curl('DELETE',$url);
    }
    
    private function PUT($url){
        return $this->curl('PUT',$url);
    }

	private function curl($mode, $curl_url,$data=NULL,$headers=NULL)
    {
    	$ch = curl_init();
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_URL, "https://api.openai.com"."$curl_url");

        $headerArray[] = "Authorization: Bearer ". env("OPENAI_KEY");

        if($mode=='POST'){

            $payload = json_encode($data);

            $headerArray[] = "Content-Type: application/json";
            $headerArray[] = 'Content-Length: ' . strlen($payload);
            
            if($headers){
                foreach($headers as $header){
                    $headerArray[] = $header;
                }
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        
        $response = curl_exec($ch);
        
        if($response === false){
            echo 'Curl error: ' . curl_error($ch);
        }
        curl_close($ch);
        
        return  $response;
    }

}
?>