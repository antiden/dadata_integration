(function (Drupal, drupalSettings) {
  Drupal.behaviors.dadataAutocomplete = {
    attach: function (context, settings) {
      const fields = drupalSettings.dadataIntegration.fields || [];

      fields.forEach(cfg => {
        const input = document.getElementById(cfg.field_id);
        if (!input) return;

        if (input.dataset.dadataAttached) return;
        input.dataset.dadataAttached = "true";

        let apiUrl = '/dadata/suggest/' + cfg.type;
        if (cfg.type === 'address' && cfg.bound && cfg.bound !== 'address') {
          apiUrl += '?bound=' + encodeURIComponent(cfg.bound);
        }

        let dropdown = null;

        // Handle user input
        input.addEventListener('input', function () {
          const query = this.value.trim();

          if (dropdown) {
            dropdown.innerHTML = "";
          }

          if (query.length < 3) {
            if (dropdown) {
              dropdown.remove();
              dropdown = null;
            }
            return;
          }

          const url = apiUrl + (apiUrl.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(query);

          fetch(url)
            .then(res => res.json())
            .then(data => {
              if (!data || !data.suggestions || data.suggestions.length === 0) {
                if (dropdown) {
                  dropdown.remove();
                  dropdown = null;
                }
                return;
              }

              if (!dropdown) {
                dropdown = document.createElement("ul");
                dropdown.classList.add("dadata-suggestions");
                input.parentNode.appendChild(dropdown);
              } else {
                dropdown.innerHTML = "";
              }

              data.suggestions.forEach((s, idx) => {
                let li = document.createElement("li");
                li.textContent = s.value;
                li.classList.add("dadata-suggestion-item");

                li.addEventListener("click", function () {
                  input.value = s.value;
                  dropdown.remove();
                  dropdown = null;
                });

                dropdown.appendChild(li);
              });

              // сбрасываем выбранный индекс
              input.dataset.activeIndex = -1;
            })
            .catch(err => console.error("DaData error", err));
        });

        // Handle keyboard navigation
        input.addEventListener('keydown', function (e) {
          if (!dropdown) return;

          const items = dropdown.querySelectorAll('.dadata-suggestion-item');
          if (items.length === 0) return;

          let activeIndex = parseInt(input.dataset.activeIndex || "-1", 10);

          if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (activeIndex < items.length - 1) activeIndex++;
          }
          else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (activeIndex > 0) activeIndex--;
          }
          else if (e.key === 'Enter') {
            if (activeIndex >= 0 && items[activeIndex]) {
              e.preventDefault();
              input.value = items[activeIndex].textContent;
              dropdown.remove();
              dropdown = null;
            }
          }

          // Обновляем подсветку
          items.forEach((item, idx) => {
            if (idx === activeIndex) {
              item.classList.add('active');
              item.scrollIntoView({ block: "nearest" });
            } else {
              item.classList.remove('active');
            }
          });

          input.dataset.activeIndex = activeIndex;
        });

        // Close dropdown when clicking outside
        document.addEventListener("click", function (e) {
          if (dropdown && !dropdown.contains(e.target) && e.target !== input) {
            dropdown.remove();
            dropdown = null;
          }
        });

        // Close dropdown on blur (with small delay to allow click)
        input.addEventListener("blur", function () {
          setTimeout(() => {
            if (dropdown) {
              dropdown.remove();
              dropdown = null;
            }
          }, 200);
        });
      });
    }
  };
})(Drupal, drupalSettings);