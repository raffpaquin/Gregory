﻿package {		import com.adobe.crypto.MD5;	import flash.net.*;		public class AMFConnection extends NetConnection {				private var _encodingKey:String = '$djlk9mfl;_xKk';				public function secureCall(command:String, responder:Responder, param:Object):void {						var timestamp:Number = Math.round((new Date()).getTime()/1000);			var signature:String = MD5.hash(String(timestamp)+_encodingKey);						var resp:Object = new Object();			resp.signature = signature;			resp.timestamp = timestamp			resp.data = param;						call(command,responder,resp);					}		}	}