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
        
<?php
$ab_privacy_link = '<a href="' . esc_url( Appointment_Booking::privacy_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Datenschutzerklärung', 'appointment-booking' ) . '</a>';
if ( 'kontakt' === $ab_variant ) : ?>
        <div class="ab-intro">
            <p><?php esc_html_e( 'Wir freuen uns, dass Sie Kontakt mit uns aufnehmen wollen. Bitte nutzen Sie das folgende Formular, wir melden uns baldmöglichst bei Ihnen.', 'appointment-booking' ); ?></p>
        </div>

        <form id="anfrage-form" class="kontakt-form">

            <!-- Anti-spam honeypot: hidden off-screen, must stay empty for real users -->
            <div class="form-group form-group-website" aria-hidden="true">
                <input type="text" id="anfrage-website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-group">
                <label for="anfrage-name"><?php esc_html_e( 'Ihr Name', 'appointment-booking' ); ?> *</label>
                <input type="text" id="anfrage-name" name="anfrage_name" autocomplete="name" required>
            </div>

            <div class="form-group">
                <label for="anfrage-email"><?php esc_html_e( 'Ihre E-Mail-Adresse', 'appointment-booking' ); ?> *</label>
                <input type="email" id="anfrage-email" name="anfrage_email" autocomplete="email" required>
            </div>

            <div class="form-group">
                <label for="anfrage-company"><?php esc_html_e( 'Ihr Unternehmen', 'appointment-booking' ); ?></label>
                <input type="text" id="anfrage-company" name="anfrage_company" autocomplete="organization">
            </div>

            <div class="form-group">
                <label for="anfrage-reason"><?php esc_html_e( 'Grund Ihrer Anfrage', 'appointment-booking' ); ?> *</label>
                <select id="anfrage-reason" name="anfrage_reason" aria-controls="anfrage-objekt" data-object-index="<?php echo (int) Appointment_Booking::KONTAKT_OBJECT_REASON; ?>" required>
                    <option value=""><?php esc_html_e( '- Bitte auswählen -', 'appointment-booking' ); ?></option>
                    <?php foreach ( $ab_reasons as $ab_reason ) : /* Wert bleibt deutsch (Prüfung und interne Mail), nur die Anzeige wird übersetzt */ ?>
                    <option value="<?php echo esc_attr( $ab_reason ); ?>"><?php echo esc_html( __( $ab_reason, 'appointment-booking' ) ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ab-objekt" id="anfrage-objekt" hidden>
                <p class="ab-note"><?php esc_html_e( 'Bei Anfragen zu einem konkreten Objekt, das zu einem der von der ServiceInvest verwalteten Fonds gehört, empfehlen wir Ihnen, sich direkt an den verantwortlichen Asset- oder Property Manager zu wenden. Dort kann Ihnen noch schneller ohne Umweg geholfen werden.', 'appointment-booking' ); ?></p>
                <fieldset>
                    <legend><?php esc_html_e( 'Objektadresse (Pflichtangabe)', 'appointment-booking' ); ?></legend>
                    <div class="form-group">
                        <label for="anfrage-street"><?php esc_html_e( 'Straße, Hausnummer', 'appointment-booking' ); ?> *</label>
                        <input type="text" id="anfrage-street" name="anfrage_street" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="anfrage-unit"><?php esc_html_e( 'Einheit/Stockwerk', 'appointment-booking' ); ?></label>
                        <input type="text" id="anfrage-unit" name="anfrage_unit" autocomplete="off">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="anfrage-zip"><?php esc_html_e( 'Postleitzahl', 'appointment-booking' ); ?> *</label>
                            <input type="text" id="anfrage-zip" name="anfrage_zip" inputmode="numeric" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="anfrage-city"><?php esc_html_e( 'Ort', 'appointment-booking' ); ?> *</label>
                            <input type="text" id="anfrage-city" name="anfrage_city" autocomplete="off">
                        </div>
                    </div>
                </fieldset>
            </div>

            <div class="form-group">
                <label for="anfrage-message"><?php esc_html_e( 'Ihre Anfrage', 'appointment-booking' ); ?> *</label>
                <textarea id="anfrage-message" name="anfrage_message" rows="6" required></textarea>
            </div>

            <div class="form-group checkbox-group">
                <input type="checkbox" id="privacy-consent-anfrage" required>
                <label for="privacy-consent-anfrage"><?php
                    /* translators: %s: Link "Datenschutzerklärung" */
                    printf( esc_html__( 'Ich habe die %s zur Kenntnis genommen und bin damit einverstanden, dass meine persönlichen Daten erfasst, gespeichert und zur Bearbeitung meiner Anfrage verwendet werden.', 'appointment-booking' ), $ab_privacy_link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?> *</label>
            </div>

            <button type="submit" class="btn-submit"><?php esc_html_e( 'Anfrage senden', 'appointment-booking' ); ?></button>

        </form>
<?php else : ?>
        <form id="anfrage-form">

            <!-- Anti-spam honeypot: hidden off-screen, must stay empty for real users -->
            <div class="form-group form-group-website" aria-hidden="true">
                <input type="text" id="anfrage-website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-group">
                <label for="anfrage-name"><?php esc_html_e( 'Name', 'appointment-booking' ); ?> *</label>
                <input type="text" id="anfrage-name" name="anfrage_name" placeholder="<?php esc_attr_e( 'Ihr vollständiger Name', 'appointment-booking' ); ?>" required>
            </div>

            <div class="form-group">
                <label for="anfrage-company"><?php esc_html_e( 'Unternehmensname', 'appointment-booking' ); ?> *</label>
                <input type="text" id="anfrage-company" name="anfrage_company" placeholder="<?php esc_attr_e( 'Ihr Unternehmensname', 'appointment-booking' ); ?>" required>
            </div>

            <?php if ( empty( $ab_hide_investor ) ) : ?>
            <div class="form-group">
                <label for="anfrage-investor-type"><?php esc_html_e( 'Anlegertyp', 'appointment-booking' ); ?> *</label>
                <select id="anfrage-investor-type" name="anfrage_investor_type" required>
                    <option value=""><?php esc_html_e( 'Bitte auswählen', 'appointment-booking' ); ?></option>
                    <?php foreach ( array( 'Versorgungswerk', 'Pensionskasse', 'Pensionsfonds', 'Staatsfonds', 'Versicherungsgesellschaft', 'Kreditinstitut', 'Stiftung' ) as $ab_type ) : ?>
                    <option value="<?php echo esc_attr( $ab_type ); ?>"><?php echo esc_html( __( $ab_type, 'appointment-booking' ) ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="anfrage-email"><?php esc_html_e( 'E-Mail', 'appointment-booking' ); ?> *</label>
                <input type="email" id="anfrage-email" name="anfrage_email" placeholder="<?php esc_attr_e( 'ihre@email.de', 'appointment-booking' ); ?>" required>
            </div>

            <div class="form-group">
                <label for="anfrage-phone"><?php esc_html_e( 'Telefon', 'appointment-booking' ); ?></label>
                <input type="tel" id="anfrage-phone" name="anfrage_phone" placeholder="+49 123 456789">
            </div>

            <div class="form-group">
                <label for="anfrage-message"><?php esc_html_e( 'Ihre Anfrage', 'appointment-booking' ); ?> *</label>
                <textarea id="anfrage-message" name="anfrage_message" placeholder="<?php esc_attr_e( 'Bitte beschreiben Sie Ihr Anliegen...', 'appointment-booking' ); ?>" rows="6" required></textarea>
            </div>

            <div class="form-group checkbox-group">
                <input type="checkbox" id="privacy-consent-anfrage" required>
                <label for="privacy-consent-anfrage"><?php
                    /* translators: %s: Link "Datenschutzerklärung" */
                    printf( esc_html__( 'Ich habe die %s zur Kenntnis genommen und bin damit einverstanden, dass meine persönlichen Daten erfasst, gespeichert und zur werblichen Kontaktaufnahme verwendet werden.', 'appointment-booking' ), $ab_privacy_link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?> *</label>
            </div>

            <button type="submit" class="btn-submit"><?php esc_html_e( 'Anfrage senden', 'appointment-booking' ); ?></button>

        </form>
<?php endif; ?>

        <!-- Success Message -->
        <div class="success-message" id="success-message-anfrage">
            <div class="success-icon">✓</div>
            <h2><?php esc_html_e( 'Anfrage erfolgreich gesendet!', 'appointment-booking' ); ?></h2>
            <p><?php esc_html_e( 'Vielen Dank für Ihre Anfrage. Wir werden uns schnellstmöglich bei Ihnen melden.', 'appointment-booking' ); ?></p>
            <button type="button" class="btn-new-booking" id="btn-new-anfrage"><?php esc_html_e( 'Neue Anfrage senden', 'appointment-booking' ); ?></button>
        </div>
        
        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loading-overlay-anfrage">
            <div class="spinner"></div>
        </div>
        
    </div>
    
</div>
