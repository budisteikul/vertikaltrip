<?php
namespace budisteikul\vertikaltrip\Helpers;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use budisteikul\vertikaltrip\Models\Contact;
use budisteikul\vertikaltrip\Models\Message;
use budisteikul\vertikaltrip\Helpers\GeneralHelper;
use budisteikul\vertikaltrip\Helpers\FirebaseHelper;
use budisteikul\vertikaltrip\Helpers\BookingHelper;

use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\AES;

class WhatsappHelper {

    public function messages($id)
    {
        $contact = Contact::where('id',$id)->firstOrFail();
        $messages = Message::where('contact_id',$contact->id)->orderBy('created_at','desc')->get();

        $output = '';
        foreach($messages as $message)
        {
            $style1 = 'card bg-light mb-2';
            if($message->from==null)
            {
                $style1 = 'card text-white bg-success mb-2';
            }

            $message_text = '';
            if($message->type=="text")
            {
                $message_text = json_decode($message->text)->body;
            }

            if($message->type=="image")
            {
                $image = json_decode($message->image);
                $image_link = '';
                if(isset($image->link)) $image_link = $image->link;
                $image_text = '<img src="'.$image_link.'" class="img-thumbnail mb-2" style="max-height: 200px;">';
                $message_text = $image_text;
                if(isset($image->caption)) $message_text = $image_text.'<br />'. $image->caption;
            }

            if($message->type=="reaction")
            {
                $message_text = json_decode($message->reaction)->emoji;
            }

            if($message->type=="template")
            {
                $message_text = "template : ". json_decode($message->template)->name;
            }

            if($message->type=="interactive")
            {
                $message_text = json_decode($message->interactive)->nfm_reply->response_json;
            }

            $output .= '<div class="'.$style1.'" >
                            <div class="card-body">
                                <p class="card-text mb-0">'. nl2br($message_text) .'</p>
                                <small>'.GeneralHelper::dateFormat($message->created_at,2).'</small>
                                <br />
                                <small>'.$message->status.'</small>
                            </div>
                        </div>';
        }

        FirebaseHelper::write('messages/'.$contact->id,$output);
    }

    public function whatsapp_to_booking_json($json)
    {
        $contact = $json->entry[0]->changes[0]->value->contacts[0];
        $message = $json->entry[0]->changes[0]->value->messages[0];

        $name = null;
        if(isset($contact->profile->name)) $name = $contact->profile->name;
        $contact_id = $this->contact($contact->wa_id,$name);

        $data_flow = $message->interactive->nfm_reply->response_json;
        
        $data = [
                "booking_confirmation_code" => BookingHelper::get_ticket(),
                "booking_channel" => "Whatsapp",
                "booking_note" => $data_flow->more_details,
                "tour_name" => $data_flow->tour_name,
                "tour_date" => $data_flow->date." ".$data_flow->time.":00",
                "participant_name" => $name,
                "participant_phone" => $contact->wa_id,
                "participant_email" => "",
                "participant_total" => $data_flow->participant,
                "product_id" => $data_flow->bokun_id
            ];

        $booking_json = (object)$data;
        return $booking_json;
    }

    function encryptResponse($response, $aesKeyBuffer, $initialVectorBuffer)
    {
        // Flip the initialization vector
        $flipped_iv = ~$initialVectorBuffer;

        // Encrypt the response data
        $cipher = openssl_encrypt(json_encode($response), 'aes-128-gcm', $aesKeyBuffer, OPENSSL_RAW_DATA, $flipped_iv, $tag);
        return base64_encode($cipher . $tag);
    }


    public function decryptRequest($body)
    {
        $privatePem = Storage::disk('gcs')->get('credentials/whatsapp/private.pem');
        $encryptedAesKey = base64_decode($body['encrypted_aes_key']);
        $encryptedFlowData = base64_decode($body['encrypted_flow_data']);
        $initialVector = base64_decode($body['initial_vector']);

        // Decrypt the AES key created by the client
        $rsa = RSA::load($privatePem,env("REDIS_PASSWORD"))
            ->withPadding(RSA::ENCRYPTION_OAEP)
            ->withHash('sha256')
            ->withMGFHash('sha256');

        $decryptedAesKey = $rsa->decrypt($encryptedAesKey);
        if (!$decryptedAesKey) {
            throw new Exception('Decryption of AES key failed.');
        }

        // Decrypt the Flow data
        $aes = new AES('gcm');
        $aes->setKey($decryptedAesKey);
        $aes->setNonce($initialVector);
        $tagLength = 16;
        $encryptedFlowDataBody = substr($encryptedFlowData, 0, -$tagLength);
        $encryptedFlowDataTag = substr($encryptedFlowData, -$tagLength);
        $aes->setTag($encryptedFlowDataTag);

        $decrypted = $aes->decrypt($encryptedFlowDataBody);
        if (!$decrypted) {
            throw new Exception('Decryption of flow data failed.');
        }

        return [
            'decryptedBody' => json_decode($decrypted, true),
            'aesKeyBuffer' => $decryptedAesKey,
            'initialVectorBuffer' => $initialVector,
        ];
    }

    public function contact($wa_id,$name=null,$shoppingcart_id=null)
    {
        $wa_id = GeneralHelper::phoneNumber($wa_id);
        $contact = Contact::where('wa_id',$wa_id)->first();
        if($contact)
        {
            if($name!=null)
            {
                $contact->name = $name;
                
            }
            if($shoppingcart_id!=null)
            {
                $contact->shoppingcart_id = $shoppingcart_id;

            }
            $contact->save();
            return $contact->id;
        }
        else
        {
            $contact = new Contact;
            $contact->wa_id = $wa_id;
            if($name!=null)
            {
                $contact->name = $name;
            }
            if($shoppingcart_id!=null)
            {
                $contact->shoppingcart_id = $shoppingcart_id;
            }
            $contact->save();
            return $contact->id;
        }
    }

    public function check_wa_id($message_id)
    {
        $message_id = Message::where('message_id',$message_id)->first();
        if($message_id)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    

    public function setStatusMessage($wa_id,$status)
    {
        $message = Message::where('message_id',$wa_id)->first();
        if($message)
        {
            if($message->status!="read" && $status=="delivered")
            {
                $message->status = $status;
                $message->save();
            }
            
            if($status=="read")
            {
                $message->status = $status;
                $message->save();
            }
            self::messages($message->contact_id);
        }
    }



    public function saveInboundMessage($json)
    {
        $contact = $json->entry[0]->changes[0]->value->contacts[0];
        $message = $json->entry[0]->changes[0]->value->messages[0];

        $name = null;
        if(isset($contact->profile->name)) $name = $contact->profile->name;
        $contact_id = $this->contact($contact->wa_id,$name);

        $from = null;
        $to = null;
        $type = null;
        $message_id = null;
        $context = null;
        $text = null;
        $image = null;
        $template = null;
        $reaction = null;
        $order = null;
        $interactive = null;
        $status = null;

        if(isset($message->id)) $message_id = $message->id;
        if(isset($message->to)) $to = $message->to;
        if(isset($message->type)) $type = $message->type;
        if(isset($message->from)) $from = $message->from;
        if(isset($message->context)) $context = $message->context;
        if(isset($message->text)) $text = $message->text;
        if(isset($message->image)) $image = $message->image;
        if(isset($message->template)) $template = $message->template;
        if(isset($message->reaction)) $reaction = $message->reaction;
        if(isset($message->order)) $order = $message->order;
        if(isset($message->interactive)) $interactive = $message->interactive;

        $message = new Message;
        $message->contact_id = $contact_id;
        $message->message_id = $message_id;
        $message->from = $from;
        $message->to = $to;
        $message->type = $type;
        $message->context = json_encode($context);
        $message->text = json_encode($text);
        $message->image = json_encode($image);
        $message->template = json_encode($template);
        $message->reaction = json_encode($reaction);
        $message->order = json_encode($order);
        $message->interactive = json_encode($interactive);
        $message->status = $status;
        $message->save();

        self::messages($contact_id);

    }

    public function saveOutboundMessage($message)
    {
        $contact_id = $this->contact($message->to);

        $from = null;
        $to = null;
        $type = null;
        $message_id = null;
        $context = null;
        $text = null;
        $image = null;
        $template = null;
        $reaction = null;
        $order = null;
        $interactive = null;
        $status = "sent";

        if(isset($message->id)) $message_id = $message->id;
        if(isset($message->to)) $to = $message->to;
        if(isset($message->type)) $type = $message->type;
        if(isset($message->from)) $from = $message->from;
        if(isset($message->context)) $context = $message->context;
        if(isset($message->text)) $text = $message->text;
        if(isset($message->image)) $image = $message->image;
        if(isset($message->template)) $template = $message->template;
        if(isset($message->reaction)) $reaction = $message->reaction;
        if(isset($message->order)) $order = $message->order;
        if(isset($message->interactive)) $interactive = $message->interactive;

        $message = new Message;
        $message->contact_id = $contact_id;
        $message->message_id = $message_id;
        $message->from = $from;
        $message->to = $to;
        $message->type = $type;
        $message->context = json_encode($context);
        $message->text = json_encode($text);
        $message->image = json_encode($image);
        $message->template = json_encode($template);
        $message->reaction = json_encode($reaction);
        $message->order = json_encode($order);
        $message->interactive = json_encode($interactive);
        $message->status = $status;
        $message->save();

        self::messages($contact_id);
    }

    public function sendTemplate($to,$template_name,$components=null,$code="en")
    {

        if($components==null)
        {
            $data = (object)[
                "messaging_product" => "whatsapp",
                "to" => $to,
                "type" => "template",
                "template" => (object)[
                        "name" => $template_name,
                        "language" => (object)[
                            "code" => $code
                        ]
                    ]
                ];
        }
        else
        {
            $data = (object)[
                "messaging_product" => "whatsapp",
                "to" => $to,
                "type" => "template",
                "template" => (object)[
                        "name" => $template_name,
                        "language" => (object)[
                            "code" => $code
                        ],
                        "components" => $components
                    ]
                ];
        }

        $whatsapp = json_decode($this->POST('/'.env("META_BUSINESS_ID").'/messages',$data));
        
        if(isset($whatsapp->error))
        {
            print_r($whatsapp->error);
            exit();
        }
        
        if(isset($whatsapp->messages[0]->id))
        {
            $data->id = $whatsapp->messages[0]->id;
            self::saveOutboundMessage($data);
            return $whatsapp;
        } 
        return '';
    }

    public function sendLocation($to='',$latitude='',$longitude='',$name='',$address='')
    {
        $data = (object)[
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "location",
            "location" => (object)[
                "latitude" => $latitude,
                "longitude" => $longitude,
                "name" => $name,
                "address" => $address
            ]
        ];

        $whatsapp = json_decode($this->POST('/'.env("META_BUSINESS_ID").'/messages',$data));
        
        if(isset($whatsapp->messages[0]->id))
        {
            $data->id = $whatsapp->messages[0]->id;
            self::saveOutboundMessage($data);
            return $whatsapp;
        } 
        return '';
    }

    public function sendContact($to,$json)
    {
        $data = [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "contacts",
            "contacts" => $json
        ];

       
        $whatsapp = json_decode($this->POST('/'.env("META_BUSINESS_ID").'/messages',$data));
        
        if(isset($whatsapp->messages[0]->id))
        {
            $data->id = $whatsapp->messages[0]->id;
            self::saveOutboundMessage($data);
            return $whatsapp;
        } 
        return '';
    }

    public function sendImage($to,$image_url,$caption)
    {
        $data = (object)[
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "image",
            "image" => (object)[
                "link" => $image_url,
                "caption" => $caption
            ]
        ];

        $whatsapp = json_decode($this->POST('/'.env("META_BUSINESS_ID").'/messages',$data));
        
        if(isset($whatsapp->messages[0]->id))
        {
            $data->id = $whatsapp->messages[0]->id;
            self::saveOutboundMessage($data);
            return $whatsapp;
        } 
        return '';
    }

    public function sendText($to,$text)
    {
        $data = (object)[
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "text",
            "text" => (object)[
                "body" => $text,
                "preview_url" => true
            ]
        ];

        $whatsapp = json_decode($this->POST('/'.env("META_BUSINESS_ID").'/messages',$data));
        
        if(isset($whatsapp->messages[0]->id))
        {
            $data->id = $whatsapp->messages[0]->id;
            self::saveOutboundMessage($data);
            return $whatsapp;
        } 
        return '';
    }

    public function getMedia($media_id,$from="other")
    {
        $image_id = Uuid::uuid4()->toString();
        $media = json_decode($this->GET('/'. $media_id .'/'));
        $ext = ".jpg";
        
        if($media->mime_type=="image/jpeg") $ext = ".jpg";
        if($media->mime_type=="image/jpg") $ext = ".jpg";
        if($media->mime_type=="image/png") $ext = ".png";

        if($media->mime_type=="text/plain") $ext = ".txt";
        if($media->mime_type=="application/vnd.ms-excel") $ext = ".xls";
        if($media->mime_type=="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet") $ext = ".xlsx";
        if($media->mime_type=="application/msword") $ext = ".doc";
        if($media->mime_type=="application/vnd.openxmlformats-officedocument.wordprocessingml.document") $ext = ".docx";
        if($media->mime_type=="application/vnd.ms-powerpoint") $ext = ".ppt";
        if($media->mime_type=="application/vnd.openxmlformats-officedocument.presentationml.presentation") $ext = ".pptx";
        if($media->mime_type=="application/pdf") $ext = ".pdf";

        if($media->mime_type=="video/3gp") $ext = ".3gp";
        if($media->mime_type=="video/mp4") $ext = ".mp4";


        $headerArray[] = "Authorization: Bearer ". env("META_WHATSAPP_TOKEN");
        $headerArray[] = "Accept-Language:en-US,en;q=0.5";
        $headerArray[] = "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $media->url);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        $contents = curl_exec($ch);
        curl_close($ch);
        

        Storage::disk('gcs')->put( 'whatsapp/'.$from.'/'.$image_id.$ext, $contents);
        $media->url = config("site.whatsapp_storage").'/'.$from .'/'.$image_id.$ext;
        return $media;
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
        curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/v19.0/'."$curl_url");

        $headerArray[] = "Authorization: Bearer ". env("META_WHATSAPP_TOKEN");

        if($mode=='POST'){

            $payload = json_encode($data);

            $headerArray[] = "Content-Type: application/json";
            
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
            //echo 'Curl error: ' . curl_error($ch);
        }

        curl_close ($ch);
        return  $response;
    }

}
?>