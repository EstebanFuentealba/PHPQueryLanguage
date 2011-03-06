<?PHP
class array2xml {
	// version 0.1
	// coder: Mustafa Turan
	private $arr;

	public function __construct(){
		$this->arr = array();
	}
	public function __destruct(){
		$this->arr = null;
	}
	public function setArr($arr) {
		if(is_array($arr))
			$this->arr = $arr;
	}
	public function createXML() {
		$xml_data = '<?xml version="1.0" encoding="utf-8"?>';
		$xml_data .= '<domain>';
		$xml_data .= $this->array2_recursiveString($this->arr);
		$xml_data .= '</domain>';
		return $xml_data;
	}
	private function array2_recursiveString($arr){
		$str = '';
		if(is_array($arr)){
			foreach ($arr as $key=>$value){
				$r_key = str_replace("#","",$key);
				if(is_array($value)){
					if(is_numeric($r_key)) $r_key = "transaction";
					$str .= '<' . $r_key . '>'.(($r_key=="text")?"<![CDATA[":"");
					$str .= $this->array2_recursiveString($value);
					$str .= (($r_key=="text")?"]]>":"").'</' . $r_key . '>';
				}
				else{
					if(!is_numeric($r_key)){
						$value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');		
						$str .= '<' . $r_key . '>'.(($r_key=="text")?"<![CDATA[":"") . $value .(($r_key=="text")?"]]>":""). '</' . $r_key . '>';
					}
				}
			}
		}
		return $str;
	}
}
?>