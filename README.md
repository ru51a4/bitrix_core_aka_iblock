iblock:  
&nbsp;&nbsp;&nbsp;id name parent_id left right depth slug   
iblock_elements:  
&nbsp;&nbsp;&nbsp;id name iblock_id slug  
iblock_properties:  
&nbsp;&nbsp;&nbsp;id is_number is_multy name iblock_id  
iblock_prop_value:  
&nbsp;&nbsp;&nbsp;id value value_number prop_id el_id slug   
***фича***  
store  
id name adress  
count_store  
id store_id product_id count  
item_prop_values  
id count_store_id prop_id value value_number

demo - http://188.120.245.72:8082/admin [ru51a4@mail.ru:12345678]
   

```
sudo docker-compose run myapp php artisan migrate:refresh
sudo docker-compose up
```
  
