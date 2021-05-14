<?php

//this process connects to the EbayAPi with GetCategoryInfo method.
//and get the categories info to store the custom post type products category.
//so you can list your products by the ebay category on your wordpress.
//this process use an aux table calls parms_ebay_wc wich contains all the father ebay categories.
//this code goes into the functions.php!



//category tasks
add_action('loadCategories', 'loadCategories');
function loadCategories() {

	//open File to write log. if it not exist it tries to create the file.
	$file = fopen("log.txt", "w+");

	//open conection
	$enlace = conexiondb();

	//this string contains the log
	$start_date_log = "---------------------------------------- \n START PROCESS AT: ";
	$start_date_log .= date("F j, Y, g:i:s:v a") . "\n"; 
	$start_date_log .= "---------------------------------------- \n";

	fwrite($file, $start_date_log);

	$trunc_parms_ebay_wc = "TRUNCATE TABLE parms_ebay_wc";
	fwrite($file, date("g:i:s:v") . $trunc_parms_ebay_wc . "\n");
	
	if (!mysqli_query($enlace, $trunc_parms_ebay_wc)) {
		fwrite($file, "Truncate table error". mysqli_error($enlace));
	} 

	$query_delete_tax_and_terms = "DELETE p, pa	FROM wp_lmjz_term_taxonomy p	";
	$query_delete_tax_and_terms .= "JOIN wp_lmjz_terms pa ON pa.term_id = p.term_id ";
	$query_delete_tax_and_terms .= "WHERE p.taxonomy='YOUR_CUSTOM_TAXONOMY'";

	if (!mysqli_query($enlace, $query_delete_tax_and_terms)) {
		fwrite($file, "Delete table taxonomies and terms has failure.". mysqli_error($enlace));
	} 


	//this query will retrive every published products with existing stock.
	$query = "SELECT DISTINCT(j.meta_value )
			FROM wp_lmjz_postmeta j
			INNER JOIN (
			SELECT *
			FROM wp_lmjz_posts po
			WHERE po.post_status = 'publish' AND po.post_type = 'product'
			) p
			INNER JOIN (
			SELECT *
			FROM wp_lmjz_postmeta
			WHERE meta_key = '_stock_status' AND meta_Value = 'instock'
			) j2
			ON p.id = j.post_id AND p.id = j2.post_id AND j.post_id = j2.post_id
			WHERE j.meta_key = '_ebay_category_1_id'";

	fwrite($file, "Query update cats " . $query . "\n");

	$resultado = mysqli_query($enlace, $query) or die("error".mysqli_error($enlace));

	$total_querys = 0;


	while ($row = mysqli_fetch_array($resultado)) {
		$idCat = $row[0];

		fwrite($file, "Iterating results. Let's hit the ebay API.". "\n");

		//defines the URL with your ebay ID.
	 	$url= 'https://open.api.ebay.com/Shopping?callname=GetCategoryInfo&responseencoding=XML&appid=EBAY-ID&siteid=0&CategoryID='.$idCat.'&version=1119';


	 	//store the results
		$sXML = download_page($url);

		//generates a XML with the ebay response.
		$oXML = simplexml_load_string($sXML);

		//and convert the xml into an array to process
		$objArr = xmlToArray($oXML);

		fwrite($file, "Ready. Partitioning results.". "\n");

		$treeCat_name = explode(":", $objArr['GetCategoryInfoResponse']['CategoryArray']['Category']['CategoryNamePath']['value']);
		$treeCat_nro = explode(":", $objArr['GetCategoryInfoResponse']['CategoryArray']['Category']['CategoryIDPath']['value']);

		$treeSname = array_slice($treeCat_name, 2); 
		$treeSnro = array_slice($treeCat_nro, 2);

		for ($i=1; $i<count($treeSname); $i++) {

			$ebay_id = $treeSnro[$i];
			$nivel = $i;
			$id_partner_ebay = $treeSnro[$i-1];
			$cat_name = $treeSname[$i];

			fwrite($file, $ebay_id ."---".$nivel."---". $id_partner_ebay."---". $cat_name. "\n");		

			if (exist_row($ebay_id, $nivel, $id_partner_ebay, $cat_name, $enlace) == false) {
				$query = "INSERT INTO parms_ebay_wc (id_ebay, nivel, partner, name) VALUES ($ebay_id, $nivel, $id_partner_ebay, '$cat_name')";

				fwrite($file, "query insert in parms_ebay_wc : " . $query. "\n");

				mysqli_query($enlace, $query) or die ("Error".mysqli_error($enlace));
			}		
		}		
		$total_querys++;		
	} 

	fwrite($file, "Rows Inserted:  " . $total_querys. "\n");

	//Call to register new Categories 
	register_new_terms($file);
	//categorizing products to ebay categories
	addPosTOcategories($file);

	fwrite($file, "______________________________________________\n");
	fwrite($file, "END PROCESS AT: ". date("F j, Y, g:i:s:v a") . "\n");
	fwrite($file, "______________________________________________\n");

	fclose($file);
}


?>
