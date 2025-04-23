<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://public.kiotapi.com/products',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_HTTPHEADER => array(
    'Authorization: bearer eyJhbGciOiJSUzI1NiIsInR5cCI6ImF0K2p3dCJ9.eyJuYmYiOjE3NDUwNzM0NDYsImV4cCI6MTc0NTE1OTg0NiwiaXNzIjoiaHR0cDovL2lkLmtpb3R2aWV0LnZuIiwiY2xpZW50X2lkIjoiYjQ1NTBkOTctNDgwMy00Yjg2LWJhY2EtY2EwMGQ0NjVlZDNiIiwiY2xpZW50X1JldGFpbGVyQ29kZSI6InRydW5ndGVzdGt2IiwiY2xpZW50X1JldGFpbGVySWQiOiI1MDA3NzUxNDEiLCJjbGllbnRfVXNlcklkIjoiMTk4NjMxIiwiY2xpZW50X1NlbnNpdGl2ZUFwaSI6IlRydWUiLCJjbGllbnRfR3JvdXBJZCI6IjI4IiwiaWF0IjoxNzQ1MDczNDQ2LCJzY29wZSI6WyJQdWJsaWNBcGkuQWNjZXNzIl19.J8r5nwkUJRT3879i-PiXk9rXviFulzAoGWTkvShqpzlG6akkDSvASoOyrhB4ZAEyjd5xjfbSwCPTButa1p2SS0uWIIxIA5jUPZ276DSAMIth2V0KORsk-JoI54J2_eUA5Qbaz0HBhyEvhzuxkTn13Rq7xgnl67bmuPFd9KciMOe88N7iAVYE_KfD3i23IeuE3kje-oxW3F9P4tbxKeq9IlaRyW3Ss-MvJ_FVvxHpkN9arf6wDPecyHFJfEZNGHDcwjRV_c2fn6qzBftRJggaoCx0COkNflNoUSXBRqM4qx49aRDAScfIbT-aNJr-JjxQ9MPBdg0Nzf-xy4J1y-SAlQ',
    'Retailer: trungtestkv',
    'Cookie: ss-id=SgthQv0lKxbtIUap2otq; ss-pid=AfVhbQQtyM46x5liQIFS'
  ),
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;
