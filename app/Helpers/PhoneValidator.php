<?php
	Class PhoneValidator{

		private $phone;

		public function __construct($phone){

				$this->phone = $phone;
		}

		public function validate(){

			if(preg_match("/[a-z]/i", $this->phone)){
	  		  return -1;
			}else{
				return $this->appender();
			}	
		} 

		public function appender()
		{
			$code = substr($this->phone, 0, 4);
			if($code != "+923"){
				$phone = substr($this->phone, 2);
				$phone = "+923".$phone;
				 $this->phone = $phone;
			}
			
			return $this->cleaner();
		}

		public function cleaner(){

			$phone = preg_replace('/[^A-Za-z0-9\-]/', '', $this->phone);
			$phone  = str_replace("-","",$phone);
			$phone = "+".$phone;
			if (strlen($phone) != 13) {
				return -1;
			}
			$this->phone = $phone;

			return $this->get();
			
		}

		public function get()
		{
			return $this->phone;
		}
	}
?>