// Function to calculate and display the estimated cost.
function calculateEstimate() {
  // grab values
  const pages       = parseFloat(document.getElementById('pages_in_original').value) || 0;
  const sets        = parseFloat(document.getElementById('number_of_sets').value)    || 0;
  const printCopies = document.getElementById('print_copies_in').value;
  const pageType    = document.getElementById('page_type').value;
  const layout      = document.getElementById('page_layout').value;

  // need at least pages & sets
  if (pages <= 0 || sets <= 0) {
    return document.getElementById('estimatedCost').style.display = 'none';
  }

  // base cost per impression
  let baseCost;
  if (printCopies === 'Black & White') baseCost = 0.03;
  else if (printCopies === 'Color')      baseCost = 0.20;
  else                                   return document.getElementById('estimatedCost').style.display = 'none';

  // Card Stock surcharge
  if (pageType === 'Card Stock') baseCost += 0.20;

  // double‑sided => 2 impressions/page
  const multiplier = layout.toLowerCase().includes('double') ? 2 : 1;

  // core cost
  const coreCost = pages * sets * multiplier * baseCost;

  // add‑ons
  let addon = 0;
  if (document.getElementById('option_tabs').checked)     addon += 0.06 * sets;   // Tabs @ $0.06/set
  if (document.getElementById('option_staple').checked)   addon += 0.01 * sets;   // Staple @ $1/100 ⇒ $0.01/set
  if (document.getElementById('option_fold').checked)     addon += 0.01 * pages;  // Fold @ $1/100 ⇒ $0.01/page
  if (document.getElementById('option_cutpaper').checked) addon += 0.01 * pages;  // Cut @ $1/100 ⇒ $0.01/page
  if (document.getElementById('option_binding').checked)  addon += 1.00 * sets;   // Binding @ $1/set

  const total = coreCost + addon;
  if (total > 0) {
    const el = document.getElementById('estimatedCost');
    el.innerHTML = `<strong>Estimated Cost: $${total.toFixed(2)}</strong><br>
                    To get exact cost, please email printing@riohondo.edu.`;
    el.style.display = 'block';
  } else {
    document.getElementById('estimatedCost').style.display = 'none';
  }
}


________________________________________________________