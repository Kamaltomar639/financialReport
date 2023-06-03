 function onExcel(tableId){
    	var table2excel = new Table2Excel();
    	table1.page.len( -1 ).draw();
		table2excel.export(document.getElementById(`${tableId}`));
		table1.page.len(10).draw();
    }