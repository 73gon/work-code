console.log(1);



function paginationInit(){

	const roleData = JSON.parse($j('#page-numbers').text());
	
	createTemplateByJson(roleData);
	
	const info = [];
	const roles = Object.keys(roleData);

	roles.forEach((role, k) => {
		const data = roleData[role];
		
		info[k] = {};
		info[k].currentPage = 1;
		info[k].maxPages = undefined;
		
		if(data.length % 5 != 0){
			info[k].maxPages =  (data.length/5) + 1;
		}else{
			info[k].maxPages =  data.length/5;
		}

		let selector = `#role_${k} .pagination-numbers`;
		console.log('selector', selector);

		const pagination = $j(`#role_${k} .pagination-numbers`).get(0);
		
		
		
		$j('#pagination-numbers .prev-page').on('click', prevPageClickHandler);
		$j('#pagination-numbers .next-page').on('click', nextPageClickHandler);
		

		const createPagination = (current) => {
			let startPage = 1;
			let pageAmount = 5;

			if(info[k].maxPages > 4) {
				if (info[k].currentPage < 4) {
					startPage = 1;
				} else if (info[k].currentPage > 3 && info[k].currentPage < maxPages - 2) {
					startPage = info[k].currentPage - 2;
				} else if (info[k].currentPage > maxPages - 3) {
					startPage = maxPages - 4;
				}
			}else{
				pageAmount = maxPages;
			}
			if(info[k].maxPages === 0){
				pagination.hide();
			}
			for (let i = startPage; i < startPage + pageAmount; i++) {
				const page = document.createElement("a");
				page.href = "#";
				page.innerText = i;
				page.classList.add("page-number");
				if (i === current) {
					page.classList.add("active");
				}
				$j(page).insertBefore($j(`#role_${k} .pagination-numbers`).find('.next-page'));
				page.addEventListener('click', pageItemClickHandler);
			}
			
			// $j(page).insertBefore($j('#role_0 .pagination-numbers').find('.next-page'));
			
		};
		createPagination(info[k].currentPage);

		pagination.addEventListener("click", (e) => {
			if (e.target.classList.contains("prev-page")) {
				if (info[k].currentPage > 1) {
					info[k].currentPage--;
				}
			} else if (e.target.classList.contains("next-page")) {
				if (info[k].currentPage < maxPages) {
					info[k].currentPage++;
				}
			} else if (e.target.classList.contains("page-number")) {
				info[k].currentPage = parseInt(e.target.innerText);
			}
			createPagination(info[k].currentPage);
		});
		
	});

}

function pageItemClickHandler(event) {
	event.preventDefault();
	const pages = document.querySelectorAll('.pagination a');
	pages.forEach(page => page.classList.remove('active'));
	event.target.classList.add('active');
}

function nextPageClickHandler(event) {
	event.preventDefault();
	const currentActive = document.querySelector('.pagination a.active');
	let nextActive = currentActive.nextElementSibling;
	while (nextActive && nextActive.tagName !== 'A') {
		nextActive = nextActive.nextElementSibling;
	}
	if (nextActive) {
		currentActive.classList.remove('active');
		nextActive.classList.add('active');
		info[k].currentPage++;
	}
}

function prevPageClickHandler(event) {
	event.preventDefault();
	const currentActive = document.querySelector('.pagination a.active');
	let prevActive = currentActive.previousElementSibling;
	while (prevActive && prevActive.tagName !== 'A') {
		prevActive = prevActive.previousElementSibling;
	}
	if (prevActive) {
		currentActive.classList.remove('active');
		prevActive.classList.add('active');
		info[k].currentPage--;
	}
}


function createTemplateByJson(json){
	
	
	const keys = Object.keys(json);
	
	let content = "";
	
	
	keys.forEach((key, index) => {
		content += "<h2>" + key + "</h2>";
	});
	
	$j(content).after('#page-numbers');
	
	
}

$j(function(){ paginationInit(); });