var getHost = window.location.protocol + "//" + window.location.host;

function complete_mpesa_openapi(){

var xhttp = new XMLHttpRequest();

  xhttp.onreadystatechange = function() {

    if (this.readyState == 4 && this.status == 200) {

	//alert(this.responseText);

		var obj = JSON.parse(this.responseText);

		if(obj.rescode == 0){

			 document.getElementById("commonname_openapi").className = "final_success";	
			 window.location = obj.final_location;			 
		}

		else{

			document.getElementById("commonname_openapi").className = "error";			

		}	
       document.getElementById("commonname_openapi").innerHTML = obj.resmsg;

    }
	if(this.status == 500)	{
		document.getElementById("commonname").className = "error";
		document.getElementById("commonname").innerHTML = "An error occured while checking the transaction status";	
		}
  };

  xhttp.open("POST", getHost+"/?scanner_action_openapi=1", true);
  xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  xhttp.send();  
}
function pay_mpesa_openapi(){

var xhttp = new XMLHttpRequest();

  xhttp.onreadystatechange = function() {

    if (this.readyState == 4 && this.status == 200) { 

		var obj2 = JSON.parse(this.responseText);

		if(obj2.rescode == 0){

			document.getElementById("commonname_openapi").className = "waiting_success";
		}

		else{

			document.getElementById("commonname_openapi").className = "error";

		}	  

	   document.getElementById("commonname_openapi").innerHTML = obj2.resmsg;
    }	
	if(this.status == 500)	{			
	document.getElementById("commonname_openapi").className = "error";	
	document.getElementById("commonname_openapi").innerHTML = "M-PESA Open API is unreachabe, please try again later";	}
  };
  xhttp.open("POST", getHost+"/?payment_action_openapi=1", true);
  xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  xhttp.send("type=STK");
}