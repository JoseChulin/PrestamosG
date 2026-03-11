// Variables globales
const testimonios = [
  {
    nombre: "Juan Pérez",
    inicial: "J",
    mensaje: "Excelente servicio, me aprobaron el préstamo en menos de 24 horas. Las tasas son muy competitivas comparado con otros lugares.",
    calificacion: 5
  },
  {
    nombre: "María González",
    inicial: "M",
    mensaje: "El proceso fue muy sencillo y la atención personalizada. Me ayudaron a elegir el mejor plan para mis necesidades.",
    calificacion: 4
  },
  {
    nombre: "Carlos Ramírez",
    inicial: "C",
    mensaje: "Llevo 3 préstamos con ellos y siempre cumplen. Los recomiendo ampliamente por su seriedad y transparencia.",
    calificacion: 5
  },
  {
    nombre: "Ana Sánchez",
    inicial: "A",
    mensaje: "Tuve un problema con un pago y me lo resolvieron inmediatamente. Muy buen servicio al cliente.",
    calificacion: 5
  },
  {
    nombre: "Roberto Mendoza",
    inicial: "R",
    mensaje: "Las mejores condiciones que encontré después de comparar con varias instituciones. Volveré a solicitarlos.",
    calificacion: 4
  }
];

let currentTestimonioIndex = 0;
let testimonioInterval;

// Función para inicializar el carrusel de testimonios
function initTestimonioCarousel() {
  renderTestimonios();
  setupTestimonioEventListeners();
  startTestimonioAutoplay();
}

// Renderizar los testimonios en el carrusel
function renderTestimonios() {
  const carousel = document.querySelector('.testimonios-carousel');
  const indicators = document.querySelector('.carousel-indicators');
  
  carousel.innerHTML = '';
  indicators.innerHTML = '';
  
  testimonios.forEach((testimonio, index) => {
    // Crear tarjeta de testimonio
    const card = document.createElement('div');
    card.className = 'testimonio-card';
    card.innerHTML = `
      <div class="testimonio-header">
        <div class="testimonio-avatar">${testimonio.inicial}</div>
        <div>
          <div class="testimonio-name">${testimonio.nombre}</div>
          <div class="testimonio-rating">${'★'.repeat(testimonio.calificacion)}${'☆'.repeat(5 - testimonio.calificacion)}</div>
        </div>
      </div>
      <div class="testimonio-content">
        "${testimonio.mensaje}"
      </div>
    `;
    carousel.appendChild(card);
    
    // Crear indicador
    const indicator = document.createElement('div');
    indicator.className = `carousel-indicator ${index === 0 ? 'active' : ''}`;
    indicator.dataset.index = index;
    indicator.addEventListener('click', () => goToTestimonio(index));
    indicators.appendChild(indicator);
  });
}

// Navegar a un testimonio específico
function goToTestimonio(index) {
  const carousel = document.querySelector('.testimonios-carousel');
  const cards = document.querySelectorAll('.testimonio-card');
  const indicators = document.querySelectorAll('.carousel-indicator');
  
  if (index < 0 || index >= cards.length) return;
  
  currentTestimonioIndex = index;
  
  // Desplazamiento suave
  cards[index].scrollIntoView({
    behavior: 'smooth',
    block: 'nearest',
    inline: 'start'
  });
  
  // Actualizar indicadores
  indicators.forEach((indicator, i) => {
    indicator.classList.toggle('active', i === index);
  });
  
  // Reiniciar autoplay
  resetTestimonioAutoplay();
}

// Navegación entre testimonios
function nextTestimonio() {
  const nextIndex = (currentTestimonioIndex + 1) % testimonios.length;
  goToTestimonio(nextIndex);
}

function prevTestimonio() {
  const prevIndex = (currentTestimonioIndex - 1 + testimonios.length) % testimonios.length;
  goToTestimonio(prevIndex);
}

// Autoplay del carrusel
//function startTestimonioAutoplay() {
//  testimonioInterval = setInterval(nextTestimonio, 5000);
//}

function resetTestimonioAutoplay() {
  clearInterval(testimonioInterval);
  startTestimonioAutoplay();
}

// Configurar event listeners
function setupTestimonioEventListeners() {
  // Botones de navegación
  document.querySelector('.testimonio.prev').addEventListener('click', prevTestimonio);
  document.querySelector('.testimonio.next').addEventListener('click', nextTestimonio);
  
  // Pausar autoplay al interactuar
  const carousel = document.querySelector('.testimonios-carousel');
  carousel.addEventListener('mouseenter', () => clearInterval(testimonioInterval));
  carousel.addEventListener('mouseleave', () => resetTestimonioAutoplay());
  carousel.addEventListener('touchstart', () => clearInterval(testimonioInterval));
  carousel.addEventListener('touchend', () => resetTestimonioAutoplay());
}

// Carrusel principal
function setupMainCarousel() {
  const carousel = document.getElementById('main-carousel');
  const items = carousel.querySelectorAll('.carousel-item');
  let currentIndex = 0;
  
  function updateCarousel() {
    const itemWidth = items[0].offsetWidth + 30; // Ancho + margen
    carousel.style.transform = `translateX(-${currentIndex * itemWidth}px)`;
  }
  
  updateCarousel();
}

// Inicialización al cargar la página
document.addEventListener('DOMContentLoaded', function() {
  initTestimonioCarousel();
  setupMainCarousel();
  
  // Redimensionar ventana
  window.addEventListener('resize', function() {
    setupMainCarousel();
  });
});

// Función para mover carruseles (usada en HTML)
function moveCarousel(direction, carouselId) {
  if (carouselId === 'main-carousel') {
    const carousel = document.getElementById(carouselId);
    const items = carousel.querySelectorAll('.carousel-item');
    let currentIndex = parseInt(carousel.dataset.currentIndex) || 0;
    
    currentIndex = Math.max(0, Math.min(currentIndex + direction, items.length - 1));
    carousel.dataset.currentIndex = currentIndex;
    
    const itemWidth = items[0].offsetWidth + 30; // Ancho + margen
    carousel.style.transform = `translateX(-${currentIndex * itemWidth}px)`;
  }
}
