# LayInvest – Poultry Business Forecasting and Planning System

**LayInvest** is a PHP + Yii2-based forecasting and planning platform developed as part of the *Pramudi Research Thesis* initiative. This project models the operational and market dynamics of Sri Lanka's commercial layer poultry industry to support financial planning, risk assessment, and forecasting for egg-based agribusinesses.

---

##  Project Overview

The poultry industry in Sri Lanka faces volatility in market-driven variables like feed cost, DOC (day-old chick) prices, and egg retail prices. LayInvest serves as a simulation and decision-support tool that allows stakeholders to forecast future trends and plan business operations accordingly.

---

##  Objectives

- Build an assisted planning tool for poultry farms
- Forecast egg, feed, and DOC price trends using Prophet
- Enable customizable financial modeling based on bird counts and production cycles
- Simulate mortality, production yield, and operational cost behavior
- Deliver cost–revenue insights under different market scenarios

---

##  Tech Stack

| Technology      | Purpose                                      |
|-----------------|----------------------------------------------|
| PHP (Standalone) | Core backend language                        |
| Yii2 Framework   | MVC structure and development framework     |
| PostgreSQL       | Relational database for models & forecasts  |
| Facebook Prophet | Market trend forecasting (price modeling)   |
| Composer         | PHP dependency management                   |

---

## Forecasting Approach

- The Prophet model is used to predict:
  -  Egg retail prices
  -  DOC (Day-Old Chick) prices
  -  Feed prices
  -  Cull Bird prices
- These inputs feed into the business logic to simulate:
  -  Egg production volumes
  -  Mortality rates (linear with flock size)
  -  Operational costs (labour, electricity, medicine, maintenance)

**Note:** Egg production and mortality are modeled using historical constants/formulas. Forecasting is applied **only to market-sensitive prices**.

---

##  Getting Started

### Requirements

- PHP 8.1+ with `pdo_pgsql` and `pgsql` extensions
- PostgreSQL 13+ running locally or remotely
- Composer installed globally
- Optional: pgAdmin for DB GUI

### Installation

```bash
# Clone the repo
git clone https://github.com/MKPNJoanne/LayInvest.git
cd LayInvest

# Install dependencies
composer install

# Configure DB
# Edit config/db.php with your PostgreSQL credentials

# Run Yii2 development server
php yii serve --port=8888
