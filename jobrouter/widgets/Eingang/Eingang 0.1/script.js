function adjustHeight(){
    const button = document.getElementById("process");
    const text = document.getElementById("description");
    const lines = text.clientHeight / text.style.lineHeight;
    button.style.height = `${40 + (lines - 1) * 20}px`;
}
window.onload = adjustHeight;

function simplyInit() {
	let currentPage = 1;
	let maxPages;
	let pageNumbers = document.getElementById("page-numbers").innerHTML;
	if(pageNumbers.length % 5 != 0){
		maxPages =  (pageNumbers.length/5) + 1;
	}else{
		maxPages =  pageNumbers.length/5;
	}
	 maxPages = 10;
    const pagination = document.getElementById('pagination-numbers');
    const pages = pagination.querySelectorAll('a:not(.prev-page):not(.next-page)');
    pages.forEach(page => page.addEventListener('click', (event) => {
		event.preventDefault();
		const pages = document.querySelectorAll('.pagination a');
		pages.forEach(page => page.classList.remove('active'));
		event.target.classList.add('active');
	}));

    const prevPage = document.querySelector('#pagination-numbers .prev-page');
    prevPage.addEventListener('click', (event) => {
        event.preventDefault();
        const currentActive = document.querySelector('.pagination a.active');
        let prevActive = currentActive.previousElementSibling;
        while (prevActive && prevActive.tagName !== 'A') {
            prevActive = prevActive.previousElementSibling;
        }
        if (prevActive) {
            currentActive.classList.remove('active');
            prevActive.classList.add('active');
            currentPage--;
        }
    });

    const nextPage = document.querySelector('#pagination-numbers .next-page');
    nextPage.addEventListener('click', (event) => {
        event.preventDefault();
        const currentActive = document.querySelector('.pagination a.active');
        let nextActive = currentActive.nextElementSibling;
        while (nextActive && nextActive.tagName !== 'A') {
            nextActive = nextActive.nextElementSibling;
        }
        if (nextActive) {
            currentActive.classList.remove('active');
            nextActive.classList.add('active');
            currentPage++;
        }
    });
    const createPagination = (current) => {
        pagination.innerHTML = "";
        let startPage = 1;
        let pageAmount = 5;

        if(maxPages > 4) {
            if (currentPage < 4) {
                startPage = 1;
            } else if (currentPage > 3 && currentPage < maxPages - 2) {
                startPage = currentPage - 2;
            } else if (currentPage > maxPages - 3) {
                startPage = maxPages - 4;
            }
        }else{
            pageAmount = maxPages;

        }
        if(maxPages === 0){
            var prev = document.getElementsByClassName("prev-page");
            var next = document.getElementsByClassName("next-page");
            prev.remove();
            next.remove();
        }
        for (let i = startPage; i < startPage + pageAmount; i++) {
            const page = document.createElement("a");
            page.href = "#";
            page.innerText = i;
            page.classList.add("page-number");
            if (i === current) {
                page.classList.add("active");
            }
            pagination.appendChild(page);
        }
        const prevBtn = document.createElement("a");
        prevBtn.href = "#";
        prevBtn.innerText = "\u003C";
        prevBtn.classList.add("prev-page");
        pagination.prepend(prevBtn);

        const nextBtn = document.createElement("a");
        nextBtn.href = "#";
        nextBtn.innerText = "\u003E";
        nextBtn.classList.add("next-page");
        pagination.appendChild(nextBtn);
    };
    createPagination(currentPage);

    pagination.addEventListener("click", (e) => {
        if (e.target.classList.contains("prev-page")) {
            if (currentPage > 1) {
                currentPage--;
            }
        } else if (e.target.classList.contains("next-page")) {
            if (currentPage < maxPages) {
                currentPage++;
            }
        } else if (e.target.classList.contains("page-number")) {
            currentPage = parseInt(e.target.innerText);
        }
        createPagination(currentPage);
    });
};

$j(document).ready(function(){
	
	if (typeof simplyTest !== 'undefined') {
		return;
	}
	window.simplyTest = true;
	
	simplyInit();
  var content = $j("#scroll");
  content.scroll(function(){
    if(content.scrollTop() > 1){
      content.animate({ scrollTop: 0 }, "slow");
    }
  });
});



