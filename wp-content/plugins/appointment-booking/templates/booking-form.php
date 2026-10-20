<!-- Appointment Booking Form -->
<div class="appointment-booking-wrapper">
    
<?php $ab_only_anfrage = ( isset( $ab_tab ) && "anfrage" === $ab_tab ); if ( ! $ab_only_anfrage ) : ?>
    <!-- Tab Navigation -->
    <div class="tab-navigation">
        <button class="tab-button active" data-tab="terminbuchung">Terminbuchung</button>
        <button class="tab-button" data-tab="anfrage">Anfrage</button>
    </div>
    
    <!-- Terminbuchung Tab -->
    <div class="appointment-booking-form tab-content active" id="terminbuchung-tab">
        
        <!-- Error Message -->
        <div class="error-message" id="error-message">
            <span class="error-text"></span>
            <button type="button" class="close-error">X</button>
        </div>
        
        <form id="appointment-booking-form">

            <!-- Anti-spam honeypot: hidden off-screen, must stay empty for real users -->
            <div class="form-group form-group-website" aria-hidden="true">
                <input type="text" id="customer-website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-row">
                <div class="form-group input-with-icon">
                    <label for="appointment-date">Datum *</label>
                    <input type="text" id="appointment-date" name="appointment_date" placeholder="Datum wählen" required readonly>
                    <span class="input-icon calendar-icon">
                        <img src="<?php echo APPOINTMENT_BOOKING_PLUGIN_URL; ?>assets/icons/calendar_697549.svg" alt="Calendar" width="20" height="20">
                    </span>
                </div>
                
                <div class="form-group input-with-icon">
                    <label for="appointment-time">Uhrzeit *</label>
                    <select id="appointment-time" name="appointment_time" required disabled>
                        <option value="">Zuerst Datum wählen</option>
                    </select>
                    <span class="input-icon clock-icon">
                        <img src="<?php echo APPOINTMENT_BOOKING_PLUGIN_URL; ?>assets/icons/clock_4251382.svg" alt="Clock" width="20" height="20">
                    </span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="customer-name">Name *</label>
                <input type="text" id="customer-name" name="customer_name" placeholder="Ihr vollständiger Name" required>
            </div>
            
            <div class="form-group">
                <label for="customer-company">Unternehmensname *</label>
                <input type="text" id="customer-company" name="customer_company" placeholder="Ihr Unternehmensname" required>
            </div>
            
            <div class="form-group">
                <label for="investor-type">Anlegertyp *</label>
                <select id="investor-type" name="investor_type" required>
                    <option value="">Bitte auswählen</option>
                    <option value="Versorgungswerk">Versorgungswerk</option>
                    <option value="Pensionskasse">Pensionskasse</option>
                    <option value="Pensionsfonds">Pensionsfonds</option>
                    <option value="Staatsfonds">Staatsfonds</option>
                    <option value="Versicherungsgesellschaft">Versicherungsgesellschaft</option>
                    <option value="Kreditinstitut">Kreditinstitut</option>
                    <option value="Stiftung">Stiftung</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="customer-email">E-Mail *</label>
                <input type="email" id="customer-email" name="customer_email" placeholder="ihre@email.de" required>
            </div>
            
            <div class="form-group">
                <label for="customer-phone">Telefon</label>
                <input type="tel" id="customer-phone" name="customer_phone" placeholder="+49 123 456789">
            </div>
            
            <div class="form-group">
                <label for="customer-message">Nachricht (optional)</label>
                <textarea id="customer-message" name="customer_message" placeholder="Zusätzliche Informationen oder Anmerkungen..."></textarea>
            </div>
            
            <div class="form-group checkbox-group">
                <input type="checkbox" id="privacy-consent" required>
                <label for="privacy-consent">Ich habe die <a href="<?php echo esc_url( home_url( '/impressum/datenschutzerklaerung/' ) ); ?>" target="_blank" rel="noopener">Datenschutzerklärung</a> zur Kenntnis genommen und bin damit einverstanden, dass meine persönlichen Daten erfasst, gespeichert und zur werblichen Kontaktaufnahme verwendet werden. *</label>
            </div>
            
            <button type="submit" class="btn-submit">Termin buchen</button>
            
        </form>
        
        <!-- Success Message -->
        <div class="success-message" id="success-message">
            <div class="success-icon">✓</div>
            <h2>Termin erfolgreich gebucht!</h2>
            <p>Vielen Dank für Ihre Buchung. Sie erhalten in Kürze eine Bestätigungs-E-Mail mit allen Details.</p>
            <div class="success-details">
                <p><strong>Datum:</strong> <span id="success-date"></span></p>
                <p><strong>Uhrzeit:</strong> <span id="success-time"></span></p>
            </div>
            <button type="button" class="btn-new-booking" id="btn-new-booking">Neuen Termin buchen</button>
        </div>
        
        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loading-overlay">
            <div class="spinner"></div>
        </div>
        
    </div>
    
<?php endif; ?>
    <!-- Anfrage Tab -->
    <div class="appointment-booking-form tab-content<?php echo $ab_only_anfrage ? " active" : ""; ?>" id="anfrage-tab">
        
        <!-- Error Message -->
        <div class="error-message" id="error-message-anfrage">
            <span class="error-text"></span>
            <button type="button" class="close-error">X</button>
        </div>
        
        <form id="anfrage-form">

            <!-- Anti-spam honeypot: hidden off-screen, must stay empty for real users -->
            <div class="form-group form-group-website" aria-hidden="true">
                <input type="text" id="anfrage-website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-group">
                <label for="anfrage-name">Name *</label>
                <input type="text" id="anfrage-name" name="anfrage_name" placeholder="Ihr vollständiger Name" required>
            </div>
            
            <div class="form-group">
                <label for="anfrage-company">Unternehmensname *</label>
                <input type="text" id="anfrage-company" name="anfrage_company" placeholder="Ihr Unternehmensname" required>
            </div>
            
            <?php if ( empty( $ab_hide_investor ) ) : ?>
            <div class="form-group">
                <label for="anfrage-investor-type">Anlegertyp *</label>
                <select id="anfrage-investor-type" name="anfrage_investor_type" required>
                    <option value="">Bitte auswählen</option>
                    <option value="Versorgungswerk">Versorgungswerk</option>
                    <option value="Pensionskasse">Pensionskasse</option>
                    <option value="Pensionsfonds">Pensionsfonds</option>
                    <option value="Staatsfonds">Staatsfonds</option>
                    <option value="Versicherungsgesellschaft">Versicherungsgesellschaft</option>
                    <option value="Kreditinstitut">Kreditinstitut</option>
                    <option value="Stiftung">Stiftung</option>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="anfrage-email">E-Mail *</label>
                <input type="email" id="anfrage-email" name="anfrage_email" placeholder="ihre@email.de" required>
            </div>
            
            <div class="form-group">
                <label for="anfrage-phone">Telefon</label>
                <input type="tel" id="anfrage-phone" name="anfrage_phone" placeholder="+49 123 456789">
            </div>
            
            <div class="form-group">
                <label for="anfrage-message">Ihre Anfrage *</label>
                <textarea id="anfrage-message" name="anfrage_message" placeholder="Bitte beschreiben Sie Ihr Anliegen..." rows="6" required></textarea>
            </div>
            
            <div class="form-group checkbox-group">
                <input type="checkbox" id="privacy-consent-anfrage" required>
                <label for="privacy-consent-anfrage">Ich habe die <a href="<?php echo esc_url( home_url( '/impressum/datenschutzerklaerung/' ) ); ?>" target="_blank" rel="noopener">Datenschutzerklärung</a> zur Kenntnis genommen und bin damit einverstanden, dass meine persönlichen Daten erfasst, gespeichert und zur werblichen Kontaktaufnahme verwendet werden. *</label>
            </div>
            
            <button type="submit" class="btn-submit">Anfrage senden</button>
            
        </form>
        
        <!-- Success Message -->
        <div class="success-message" id="success-message-anfrage">
            <div class="success-icon">✓</div>
            <h2>Anfrage erfolgreich gesendet!</h2>
            <p>Vielen Dank für Ihre Anfrage. Wir werden uns schnellstmöglich bei Ihnen melden.</p>
            <button type="button" class="btn-new-booking" id="btn-new-anfrage">Neue Anfrage senden</button>
        </div>
        
        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loading-overlay-anfrage">
            <div class="spinner"></div>
        </div>
        
    </div>
    
</div>
