window.addEventListener('DOMContentLoaded', simplifyWidget);

function simplifyWidget(){
    const outerBox = document.getElementById('outerBox');
    let maxPages = {};
    let pageAmount = {};
    let startPage = {};
    let cPage = {}; 

    role.forEach((role, index) => { 
        startPage[index] = 1;
        pageAmount[index] = 5;
        cPage[index] = 1;
        maxPages[index] = Math.ceil(info[index].length / 5);
        
        //roleCreating
        let roleContainer = document.createElement('div');
            roleContainer.setAttribute('class', 'roleContainer');
        outerBox.appendChild(roleContainer);
        let roleName = document.createElement('a');
            roleName.setAttribute('class', 'roleName');
            roleName.innerHTML = role;   
        roleContainer.appendChild(roleName);

        let buttonContainer = {};
            buttonContainer[index] = document.createElement('div');
            buttonContainer[index].setAttribute('class', 'buttonContainer');
        roleContainer.appendChild(buttonContainer[index]);

        const createButtons = (current) => { 
            buttonContainer[index].innerHTML = "";
            for(let i = (current-1) * 5; i < ((current-1) * 5) + 5; i++){
                if(i < info[index].length){
                    let button = document.createElement('button');
                        button.href = links[index][i];
                        button.innerHTML = info[index][i];
                        button.setAttribute('class', 'processButton');
                    buttonContainer[index].appendChild(button);
                    let linebreak = document.createElement('br');
                    button.appendChild(linebreak);
                    let text = document.createElement('a')
                        text.innerHTML = description[index][i];
                        text.setAttribute('class', 'description');
                    button.appendChild(text);
                }
            }
        };
        createButtons(cPage[index]);
        


        if(maxPages[index] > 1){
            let pagingContainer = {};
                pagingContainer[index] = document.createElement('div');
                pagingContainer[index].setAttribute('class', 'paginationContainer');
            roleContainer.appendChild(pagingContainer[index]);

           
            const createPagination = (current) => {
                pagingContainer[index].innerHTML = "";
                if(maxPages[index] > 4) {
                    if (cPage[index] < 4) {
                        startPage[index] = 1;
                    } else if (cPage[index] > 3 && cPage[index] < maxPages[index] - 2) {
                        startPage[index] = cPage[index] - 2;
                    } else if (cPage[index] > maxPages[index] - 3) {
                        startPage[index] = maxPages[index] - 4;
                    }
                }else{
                    pageAmount[index] = maxPages[index];
                }
                
                    const prevBtn = document.createElement("a");
                        prevBtn.href = "#0";
                        prevBtn.innerText = "\u003C";
                        prevBtn.setAttribute('class', 'prevBtn');
                    pagingContainer[index].appendChild(prevBtn);
            
                for(let i = startPage[index]; i < startPage[index] + pageAmount[index]; i++){
                    let page = document.createElement("a");
                    page.href = "#0";
                    page.innerText = i;
                    page.setAttribute('class', 'paging' + role);
                    if(i == current){
                        page.classList.add('active');
                    }
                    pagingContainer[index].appendChild(page);
                }
            
                    const nextBtn = document.createElement("a");
                        nextBtn.href = "#0";
                        nextBtn.innerText = "\u003E";
                        nextBtn.setAttribute('class', 'nextBtn');
                    pagingContainer[index].appendChild(nextBtn);
            };
            createPagination(cPage[index]);
            


            pagingContainer[index].addEventListener("click", (e) => {
                if (e.target.classList.contains("prevBtn")) {
                    if (cPage[index] > 1) {
                        cPage[index]--;
                    }
                } else if (e.target.classList.contains("nextBtn")) {
                    if (cPage[index] < maxPages[index]) {
                        cPage[index]++;
                    }
                } else if (e.target.classList.contains('paging' + role)) {
                    cPage[index] = parseInt(e.target.innerText);
                }
                createButtons(cPage[index]); 
                createPagination(cPage[index]);
            });
        }    
    })
}


const info = [
    ["Apfel", "Birne", "Erdbeere"],
    ["Hund", "Katze", "Kaninchen", "Kuh", "Huhn", "Ente", "Gans", "Frosch", "Schlange", "Eidechse", "Schildkröte", "Affe", "Gorilla", "Hund", "Katze", "Kaninchen", "Kuh", "Huhn", "Ente", "Gans", "Frosch", "Schlange", "Eidechse", "Schildkröte", "Affe", "Gorilla", "Hund", "Katze", "Kaninchen", "Kuh", "Huhn", "Ente", "Gans", "Frosch", "Schlange", "Eidechse", "Schildkröte", "Affe", "Gorilla", "Hund", "Katze", "Kaninchen", "Kuh", "Huhn", "Ente", "Gans", "Frosch", "Schlange", "Eidechse", "Schildkröte", "Affe", "Gorilla", "Löwe"],
    ["Auto", "Fahrrad", "Motorrad", "Bus", "Zug", "Flugzeug", "Boot", "U-Boot", "Helikopter", "Traktor", "LKW", "Feuerwehrauto", "Polizeiauto", "Rennwagen", "Cabrio", "Roller", "Tretroller"],
    ["Computer", "Handy", "Fernseher", "Kühlschrank", "Waschmaschine", "Geschirrspüler", "Mikrowelle", "Backofen", "Toaster", "Kaffeemaschine", "Staubsauger", "Haartrockner", "Bügeleisen", "Drucker", "Lautsprecher", "Kopfhörer", "Tablet", "Auto", "Fahrrad", "Motorrad", "Bus", "Zug", "Flugzeug", "Boot", "Fahrrad", "Motorrad", "Bus", "Zug", "Flugzeug", "Boot"],
    ["Haus", "Wohnung", "Schloss", "Villa", "Bungalow", "Mehrfamilienhaus", "Ferienhaus", "Baumhaus", "Wohnwagen", "Zelt", "Jurte", "Iglu", "Leuchtturm", "Windmühle", "Höhle", "Schiff", "Wolkenkratzer"]
    ];

             
const description = [
           ["Rundes Obst", "Weiches Obst", "Rotes Obst", "Gelbes Obst", "Weiches Obst", "Kleines Obst", "Großes Obst", "Kleine Frucht", "Tropische Frucht", "Tropische Frucht", "Kleine Frucht", "Rote Frucht", "Kleine Frucht", "Kleine Frucht", "Kleine Frucht", "Nussig", "Nussig", "Nussig"],
           ["Haustier", "Haustier", "Haustier", "Kleintier", "Kleintier", "Bauernhof-Tier", "Bauernhof-Tier", "Bauernhof-Tier", "Bauernhof-Tier", "Geflügel", "Geflügel", "Amphibie", "Reptil", "Reptil", "Reptil", "Affenartiges Tier", "Affenartiges Tier", "Großkatze"],
           ["Transportmittel", "Transportmittel", "Transportmittel", "Öffentliches Verkehrsmittel", "Öffentliches Verkehrsmittel", "Luftfahrzeug", "Wasserfahrzeug", "Unterwasserfahrzeug", "Luftfahrzeug", "Landwirtschaftliches Fahrzeug", "Schweres Fahrzeug", "Notfahrzeug", "Notfahrzeug", "Rennwagen", "Cabrio", "Kleines Fahrzeug", "Kleines Fahrzeug", "Tablet-Computer"],
           ["Elektronikgerät", "Elektronikgerät", "Elektronikgerät", "Haushaltsgerät", "Haushaltsgerät", "Haushaltsgerät", "Haushaltsgerät", "Haushaltsgerät", "Küchengerät", "Küchengerät", "Haushaltsgerät", "Haushaltsgerät", "Haushaltsgerät", "Bürogerät", "Audio-Gerät", "Audio-Gerät", "Tragbares Gerät"],
           ["Wohnraum", "Wohnraum", "Wohngebäude", "Wohngebäude", "Wohngebäude", "Wohngebäude", "Ferienwohnung", "Baumhaus", "Mobiles Zuhause", "Zelt", "Zeltartiges Wohnhaus", "Eiswohnung", "Maritimes Wohngebäude", "Historisches Gebäude", "Naturwohnung", "Transportmittel-Wohnung", "Hochhaus"]
           ];
      

const links = [
     [26, 53, 9, 17, 84, 31, 72, 55, 42, 13, 77, 68, 10, 99, 21, 48, 62, 38],
     [87, 41, 19, 25, 70, 96, 2, 50, 36, 58, 66, 29, 16, 34, 93, 60, 14, 7],
     [90, 30, 54, 91, 28, 12, 8, 71, 57, 46, 95, 18, 39, 73, 65, 51, 6, 1],
     [3, 44, 76, 75, 11, 40, 35, 98, 61, 45, 86, 88, 63, 15, 56, 22, 43, 83],
     [23, 80, 4, 79, 52, 97, 59, 5, 81, 64, 27, 92, 33, 69, 78, 20, 37, 67]
     ];
  
         
const role = ["role1", "role2", "role3", "role4", "role5"];

