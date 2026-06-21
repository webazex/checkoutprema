<?php
$this->title = 'Shopping Cart';
?>
<div class="page-container">
    <div class="page-container__customer-box">
        <h2 class="customer-box__title">
            <span class="title__txt-h2">оформлення замовлення</span>
        </h2>

        <form class="customer-box__order-form">
            <h3 class="order-form__title">
                <span class="title__txt-h3">Дані отримувача</span>
            </h3>
            <label class="order-form__label" for="fio">
                <span>піб</span>
                <input type="text" name="fio" id="fio" required="required">
            </label>
            <label class="order-form__label" for="phone">
                <span>Номер телефону</span>
                <input type="tel" name="phone" id="phone" required="required">
            </label>
            <label class="order-form__label" for="mail">
                <span>email</span>
                <input type="email" name="mail" id="mail" required="required">
            </label>
            <h3 class="order-form__title">
                <span class="title__txt-h3">Доставка</span>
            </h3>
            <label class="order-form__label" for="region">
                <span>Оберіть область</span>
                <select name="region" id="region" required="required">
                    <option></option>
                    <option>Some region 2</option>
                </select>
            </label>
            <label class="order-form__label" for="city">
                <span>Оберіть місто</span>
                <select name="city" id="city" required="required">
                    <option></option>
                    <option>Some city 2</option>
                </select>
            </label>
            <label class="order-form__label" for="place">
                <span>Оберіть відділення</span>
                <select name="place" id="place" required="required">
                    <option></option>
                    <option>Some place 2</option>
                </select>
            </label>

            <h3 class="order-form__title">
                <span class="title__txt-h3">Оплата</span>
            </h3>
            <label class="order-form__label" for="subscription">
                <span>Повна передплата</span>
                <input type="checkbox" name="subscription" id="subscription" checked="checked" required="required">
            </label>
            <button type="submit" class="order-form__btn">
                <span>Оформити замовлення</span>
            </button>
        </form>
    </div>
    <div class="page-container__cart-box">
        <h2 class="cart-box__title">
            <span class="title__txt-h2">в кошику</span>
        </h2>
        <div class="cart-box__products">
            <div class="products__product-item">
                <img src="assets/img/3.jpg" alt="" class="product-item__thumb">
                <div class="product-item__info-box">
                    <span class="info-box__product-name">Product name</span>
                    <div class="info-box__properties">
                        <div class="properties__prop-row">
                            <span class="prop-row__key">Матеріал:</span>
                            <span class="prop-row__val">Каучук</span>
                        </div>
                        <div class="properties__prop-row">
                            <span class="prop-row__key">Форма:</span>
                            <span class="prop-row__val">Загогулина)</span>
                        </div>
                        <div class="properties__prop-row">
                            <span class="prop-row__key">Розмір:</span>
                            <span class="prop-row__val">25*40</span>
                        </div>
                    </div>
                </div>
                <div class="product-item__price-box">
                    <div class="price-box__price-block">
                        <span class="price-block__price">2155</span>
                        <span class="price-block__valute">грн.</span>
                    </div>
                </div>
                <span class="product-item__remove-icon">
                                <svg width="28" height="28" viewBox="0 0 28 28" fill="none"
                                     xmlns="http://www.w3.org/2000/svg">
                                    <path d="M6.16699 0.5H21.583C24.7126 0.5 27.25 3.03738 27.25 6.16699V21.583C27.25 24.7126 24.7126 27.25 21.583 27.25H6.16699C3.03738 27.25 0.5 24.7126 0.5 21.583V6.16699C0.5 3.03738 3.03738 0.5 6.16699 0.5Z"
                                          stroke="black"/>
                                    <path d="M9.25 18.5L18.5 9.25" stroke="black"/>
                                    <path d="M18.5 18.5L9.25 9.25" stroke="black"/>
                                </svg>
                            </span>
            </div>
            <div class="products__product-item">
                <img src="assets/img/2.jpg" alt="" class="product-item__thumb">
                <div class="product-item__info-box">
                    <span class="info-box__product-name">Some other product name</span>
                    <div class="info-box__properties">
                        <div class="properties__prop-row">
                            <span class="prop-row__key">Матеріал:</span>
                            <span class="prop-row__val">Велюр</span>
                        </div>
                        <div class="properties__prop-row">
                            <span class="prop-row__key">Форма:</span>
                            <span class="prop-row__val">Загогулина хвиляста)</span>
                        </div>
                        <div class="properties__prop-row">
                            <span class="prop-row__key">Розмір:</span>
                            <span class="prop-row__val">52*40</span>
                        </div>
                    </div>
                </div>
                <div class="product-item__price-box">
                    <div class="price-box__price-block">
                        <span class="price-block__price">5651</span>
                        <span class="price-block__valute">грн.</span>
                    </div>
                </div>
                <span class="product-item__remove-icon">
                                <svg width="28" height="28" viewBox="0 0 28 28" fill="none"
                                     xmlns="http://www.w3.org/2000/svg">
                                    <path d="M6.16699 0.5H21.583C24.7126 0.5 27.25 3.03738 27.25 6.16699V21.583C27.25 24.7126 24.7126 27.25 21.583 27.25H6.16699C3.03738 27.25 0.5 24.7126 0.5 21.583V6.16699C0.5 3.03738 3.03738 0.5 6.16699 0.5Z"
                                          stroke="black"/>
                                    <path d="M9.25 18.5L18.5 9.25" stroke="black"/>
                                    <path d="M18.5 18.5L9.25 9.25" stroke="black"/>
                                </svg>
                            </span>
            </div>
        </div>
        <div class="cart-box__price-row">
            <span class="price-row__label">Разом:</span>
            <div class="price-row__price-block">
                <span class="price-block__price">5926</span>
                <span class="price-block__valute">грн.</span>
            </div>
        </div>
    </div>
</div>
