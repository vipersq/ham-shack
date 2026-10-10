<?php
/**
 * Learning tools: Morse trainer ([hamshack_morse]) and a practice quiz ([hamshack_quiz]).
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function hamshack_enqueue_learn() {
	wp_enqueue_script( 'hamshack-learn', HAMSHACK_URI . '/assets/js/learn.js', array(), HAMSHACK_VERSION, true );
	wp_localize_script(
		'hamshack-learn',
		'M6LEARN',
		array(
			'call' => strtoupper( hamshack_opt( 'callsign' ) ),
			'quiz' => hamshack_quiz_questions(),
			'i18n' => array(
				'play'      => __( 'Play', 'hamshack' ),
				'stop'      => __( 'Stop', 'hamshack' ),
				'check'     => __( 'Check', 'hamshack' ),
				'accuracy'  => __( 'Accuracy: %s%%', 'hamshack' ),
				'next'      => __( 'Great – over 90%! Ready for lesson %s.', 'hamshack' ),
				'again'     => __( 'Keep practising this lesson until you reach 90%.', 'hamshack' ),
				'newchars'  => __( 'Characters in this lesson', 'hamshack' ),
				'listening' => __( 'Listen…', 'hamshack' ),
				'correct'   => __( 'Correct!', 'hamshack' ),
				'wrong'     => __( 'It was %s', 'hamshack' ),
				'score'     => __( 'Score: %1$s / %2$s · streak %3$s', 'hamshack' ),
				'qof'       => __( 'Question %1$s of %2$s', 'hamshack' ),
				'result'    => __( 'You scored %1$s out of %2$s.', 'hamshack' ),
				'pass'      => __( 'Well done – that would be a pass. 73!', 'hamshack' ),
				'fail'      => __( 'Not quite there yet – read the explanations and try again.', 'hamshack' ),
				'restart'   => __( 'New quiz', 'hamshack' ),
				'nextq'     => __( 'Next question', 'hamshack' ),
				'finish'    => __( 'See result', 'hamshack' ),
				'sent'      => __( 'sent', 'hamshack' ),
				'you'       => __( 'you', 'hamshack' ),
			),
		)
	);
}

/**
 * [hamshack_morse]
 */
add_shortcode( 'hamshack_morse', function () {
	hamshack_enqueue_learn();
	ob_start();
	?>
	<div class="hs-app morse-app" data-hs-morse>
		<div class="morse-settings panel">
			<label><span><?php esc_html_e( 'Character speed', 'hamshack' ); ?> <b class="mono" data-out="wpm">20</b> WPM</span><input type="range" min="12" max="35" value="20" data-set="wpm"></label>
			<label><span><?php esc_html_e( 'Effective speed', 'hamshack' ); ?> <b class="mono" data-out="eff">10</b> WPM</span><input type="range" min="4" max="35" value="10" data-set="eff"></label>
			<label><span><?php esc_html_e( 'Tone', 'hamshack' ); ?> <b class="mono" data-out="tone">600</b> Hz</span><input type="range" min="400" max="900" step="10" value="600" data-set="tone"></label>
			<label><span><?php esc_html_e( 'Volume', 'hamshack' ); ?> <b class="mono" data-out="vol">50</b>%</span><input type="range" min="5" max="100" value="50" data-set="vol"></label>
		</div>
		<div class="seg morse-tabs" role="tablist">
			<button type="button" class="is-on" data-tab="koch"><?php esc_html_e( 'Koch lessons', 'hamshack' ); ?></button>
			<button type="button" data-tab="calls"><?php esc_html_e( 'Callsign copy', 'hamshack' ); ?></button>
			<button type="button" data-tab="text"><?php esc_html_e( 'Text to Morse', 'hamshack' ); ?></button>
		</div>

		<section class="morse-pane panel" data-pane="koch">
			<p class="muted"><?php esc_html_e( 'The Koch method starts with just two characters at full speed. Copy each group, and when you reach 90% move on – one new character per lesson.', 'hamshack' ); ?></p>
			<div class="app-toolbar">
				<label class="map-select"><span><?php esc_html_e( 'Lesson', 'hamshack' ); ?></span><select data-lesson></select></label>
				<label class="map-select"><span><?php esc_html_e( 'Groups', 'hamshack' ); ?></span><select data-groups><option>5</option><option selected>10</option><option>20</option></select></label>
			</div>
			<div class="koch-chars mono" data-chars></div>
			<div class="morse-actions">
				<button type="button" class="btn btn-primary" data-play-koch><?php hamshack_the_icon( 'play', 16 ); ?> <?php esc_html_e( 'Play', 'hamshack' ); ?></button>
				<button type="button" class="btn" data-stop><?php hamshack_the_icon( 'stop', 16 ); ?> <?php esc_html_e( 'Stop', 'hamshack' ); ?></button>
			</div>
			<textarea class="mono morse-input" rows="3" data-koch-input placeholder="<?php esc_attr_e( 'Type what you hear, groups separated by spaces…', 'hamshack' ); ?>" spellcheck="false" autocomplete="off"></textarea>
			<button type="button" class="btn" data-check-koch><?php hamshack_the_icon( 'check', 16 ); ?> <?php esc_html_e( 'Check', 'hamshack' ); ?></button>
			<div class="koch-result" data-koch-result></div>
		</section>

		<section class="morse-pane panel" data-pane="calls" hidden>
			<p class="muted"><?php esc_html_e( 'A random callsign is sent – type it and press Enter. Getting it right speeds things up a little, a mistake slows it down.', 'hamshack' ); ?></p>
			<div class="call-row">
				<button type="button" class="btn btn-primary" data-play-call><?php hamshack_the_icon( 'play', 16 ); ?> <?php esc_html_e( 'Next callsign', 'hamshack' ); ?></button>
				<button type="button" class="btn" data-repeat-call><?php esc_html_e( 'Repeat', 'hamshack' ); ?></button>
				<input type="text" class="mono morse-call-input" data-call-input autocomplete="off" spellcheck="false" placeholder="G4ABC">
			</div>
			<div class="call-feedback" data-call-feedback></div>
			<div class="mono muted" data-call-score></div>
		</section>

		<section class="morse-pane panel" data-pane="text" hidden>
			<textarea class="mono morse-input" rows="3" data-text-input spellcheck="false">CQ CQ DE <?php echo esc_html( strtoupper( hamshack_opt( 'callsign' ) ) ); ?> K</textarea>
			<div class="morse-actions">
				<button type="button" class="btn btn-primary" data-play-text><?php hamshack_the_icon( 'play', 16 ); ?> <?php esc_html_e( 'Play', 'hamshack' ); ?></button>
				<button type="button" class="btn" data-stop><?php hamshack_the_icon( 'stop', 16 ); ?> <?php esc_html_e( 'Stop', 'hamshack' ); ?></button>
			</div>
			<div class="morse-visual mono" data-visual></div>
		</section>

		<details class="morse-table panel">
			<summary><?php esc_html_e( 'Morse code chart', 'hamshack' ); ?></summary>
			<div class="morse-chart mono" data-chart></div>
		</details>
	</div>
	<?php
	return ob_get_clean();
} );

/**
 * [hamshack_quiz count="10"]
 */
add_shortcode( 'hamshack_quiz', function ( $atts ) {
	$atts = shortcode_atts( array( 'count' => '10' ), $atts, 'hamshack_quiz' );
	hamshack_enqueue_learn();
	ob_start();
	?>
	<div class="hs-app quiz-app panel" data-hs-quiz data-count="<?php echo (int) $atts['count']; ?>">
		<div class="quiz-top"><span class="mono muted" data-progress></span><span class="quiz-bar"><span data-bar></span></span></div>
		<div data-stage></div>
		<p class="app-note"><?php esc_html_e( 'Practice questions in the style of the UK Foundation exam, written for this site. Always check the current syllabus and licence conditions with the RSGB and Ofcom.', 'hamshack' ); ?></p>
	</div>
	<?php
	return ob_get_clean();
} );

/**
 * Question bank: [ q, [ answers… ], correct index, explanation ].
 */
function hamshack_quiz_questions() {
	$q = array(
		array( 'What does the Q-code “QTH” mean?', array( 'My location', 'My name', 'Signal report', 'Please stand by' ), 0, 'QTH = location. QRZ asks who is calling, QRT means stopping transmitting, QSL confirms receipt.' ),
		array( 'How is the letter “M” spelled in the phonetic alphabet?', array( 'Mary', 'Mike', 'Metro', 'Mexico' ), 1, 'The ITU / NATO phonetic alphabet uses MIKE for M.' ),
		array( 'Which phonetic word is used for the letter “Q”?', array( 'Queen', 'Quiz', 'Quebec', 'Quarter' ), 2, 'QUEBEC – pronounced keh-BECK.' ),
		array( 'A signal report of “59” on SSB means…', array( 'Weak and hard to read', 'Perfectly readable and very strong', 'Readable with difficulty, fair strength', 'Distorted audio' ), 1, 'R5 = perfectly readable, S9 = very strong signal. The third digit (tone) is only used for CW and digital.' ),
		array( 'What is the approximate wavelength of a signal on 145 MHz?', array( '20 m', '2 m', '70 cm', '6 m' ), 1, 'Wavelength (m) ≈ 300 ÷ frequency (MHz): 300 ÷ 145 ≈ 2.07 m – the “2 m band”.' ),
		array( 'Roughly how long is a half-wave dipole for 14.2 MHz?', array( 'About 5 m', 'About 10 m', 'About 20 m', 'About 40 m' ), 1, 'A full wavelength is 300 ÷ 14.2 ≈ 21 m, so half is about 10.5 m (slightly less in practice).' ),
		array( 'Using Ohm’s law, what current flows through a 100 Ω resistor with 12 V across it?', array( '0.12 A', '1.2 A', '8.3 A', '1200 A' ), 0, 'I = V ÷ R = 12 ÷ 100 = 0.12 A (120 mA).' ),
		array( 'A transmitter draws 2 A from a 13.8 V supply. What DC power does it use?', array( '6.9 W', '15.8 W', '27.6 W', '138 W' ), 2, 'P = V × I = 13.8 × 2 = 27.6 W.' ),
		array( 'What does a high SWR reading usually indicate?', array( 'A good match between radio and antenna', 'A mismatch – power is reflected back from the antenna', 'The battery is flat', 'The band is open' ), 1, 'SWR measures how well the antenna system matches 50 Ω. A high SWR means reflected power and a poor match.' ),
		array( 'Which piece of equipment is put in the feeder to measure SWR?', array( 'An SWR meter (bridge)', 'A multimeter on the ohms range', 'A frequency counter', 'A dummy load only' ), 0, 'An SWR meter or bridge sits between the transmitter and the feeder.' ),
		array( 'What is a dummy load used for?', array( 'To make the antenna longer', 'To test a transmitter without radiating a signal', 'To receive weak signals', 'To lower the SWR of an antenna' ), 1, 'A dummy load is a non-radiating 50 Ω resistor – ideal for tests and adjustments.' ),
		array( 'Which mode is most often used for weak-signal digital contacts on HF today?', array( 'AM', 'FT8', 'SSTV', 'Packet' ), 1, 'FT8 decodes signals far below the noise and is hugely popular on HF.' ),
		array( 'On the 20 m band, which sideband is normally used for SSB voice?', array( 'Lower sideband', 'Upper sideband', 'Both sidebands', 'Neither – FM is used' ), 1, 'By convention USB is used on 20 m and above (and on 60 m); LSB on 40, 80 and 160 m.' ),
		array( 'What is the purpose of a CTCSS tone when using a repeater?', array( 'It improves audio quality', 'It opens (accesses) the repeater', 'It reduces power', 'It identifies your callsign' ), 1, 'Many repeaters only open when they hear the correct sub-audible CTCSS tone.' ),
		array( 'A repeater listens on one frequency and…', array( 'transmits on the same frequency', 'retransmits on a different frequency at the same time', 'only works on HF', 'records messages for later' ), 1, 'Repeaters receive on the input and simultaneously retransmit on the output frequency – the difference is the “shift”.' ),
		array( 'Which layer of the ionosphere is mainly responsible for long-distance HF propagation?', array( 'D layer', 'E layer', 'F layer', 'The troposphere' ), 2, 'The F layer (F1/F2) refracts HF signals back to earth over thousands of kilometres.' ),
		array( 'Why do the lower HF bands (80 m, 160 m) work mostly at night for DX?', array( 'The D layer, which absorbs them, disappears after dark', 'The sun heats the antenna', 'Repeaters switch on at night', 'There is less noise in summer' ), 0, 'The D layer absorbs low frequencies in daylight; after sunset it fades and longer paths open.' ),
		array( 'The “greyline” is…', array( 'A type of coaxial cable', 'The band between day and night around the earth', 'A contest category', 'The noise floor on a waterfall' ), 1, 'Along the day/night terminator low-band signals often travel unusually well.' ),
		array( 'What is the solar flux index (SFI) a measure of?', array( 'The number of sunspots seen', 'Solar radio noise at 10.7 cm, a guide to ionisation', 'The temperature of the sun', 'Geomagnetic disturbance' ), 1, 'The 10.7 cm solar flux tracks ionisation: higher numbers usually mean better high-band HF conditions.' ),
		array( 'A high K-index usually means…', array( 'Excellent HF conditions', 'A disturbed geomagnetic field and poorer HF conditions', 'More sunspots', 'Tropospheric ducting' ), 1, 'K-index measures geomagnetic disturbance. Higher values often degrade HF propagation, especially on polar paths.' ),
		array( 'Which coax impedance is standard for amateur transceivers?', array( '50 Ω', '75 Ω', '300 Ω', '600 Ω' ), 0, 'Almost all amateur radios expect 50 Ω. 75 Ω is used for TV.' ),
		array( 'What happens to coax losses as frequency increases?', array( 'They decrease', 'They stay the same', 'They increase', 'They disappear above 30 MHz' ), 2, 'Feeder loss rises with frequency – good low-loss cable matters much more on 70 cm than on 80 m.' ),
		array( 'Why should a station have a good RF and safety earth?', array( 'To make the radio heavier', 'For electrical safety and to reduce RF in the shack', 'To increase the SWR', 'It is only needed for receivers' ), 1, 'A proper earth improves safety and helps keep RF out of the shack and household equipment.' ),
		array( 'If your transmissions cause interference to a neighbour’s TV, the first step is to…', array( 'Ignore it', 'Increase power', 'Investigate politely, reduce power and check filtering at both ends', 'Change your callsign' ), 2, 'Electromagnetic compatibility (EMC) problems are solved by cooperation, filtering and good practice.' ),
		array( 'What does “QRP” mean?', array( 'Low power operating', 'I am busy', 'Please send faster', 'Your signal is fading' ), 0, 'QRP is low-power operating (commonly 5 W or less on CW/digital, 10 W SSB).' ),
		array( 'What does “73” mean at the end of a contact?', array( 'Love and kisses', 'Best regards', 'Over to you', 'Stand by' ), 1, '73 = best regards. 88 is “love and kisses”.' ),
		array( 'A Maidenhead locator such as IO91 identifies…', array( 'A band plan segment', 'A grid square on the earth’s surface', 'A repeater channel', 'A contest serial number' ), 1, 'Locators divide the world into fields (IO), squares (IO91) and subsquares (IO91ab).' ),
		array( 'Before calling CQ on a frequency you should…', array( 'Call immediately – it saves time', 'Listen first and ask if the frequency is in use', 'Transmit a long tune-up carrier', 'Use full power' ), 1, 'Always listen and ask “is this frequency in use?” (QRL? on CW) before calling.' ),
		array( 'Which is the best way to tune up an antenna tuner?', array( 'At full power on a busy frequency', 'At low power, on a clear frequency, as briefly as possible', 'On the repeater output', 'With the antenna disconnected' ), 1, 'Tune at low power on a clear frequency and keep it short – or use a dummy load where possible.' ),
		array( 'What does a band plan do?', array( 'Sets the price of radios', 'Divides each band into recommended segments for different modes', 'Lists all licensed amateurs', 'Predicts propagation' ), 1, 'Band plans (from the IARU and national societies) keep CW, digital and voice operators out of each other’s way.' ),
		array( 'Which frequency is the 2 m FM simplex calling channel in the UK?', array( '145.500 MHz', '144.300 MHz', '433.500 MHz', '14.200 MHz' ), 0, '145.500 MHz (channel V40, formerly S20) is the 2 m FM calling channel; 433.500 MHz on 70 cm.' ),
		array( 'Why is it important to identify regularly with your callsign?', array( 'It is a licence requirement and lets others know who is transmitting', 'It makes the signal stronger', 'It is only needed in contests', 'To confuse other stations' ), 0, 'Identification is a licence requirement in the UK and good operating practice everywhere.' ),
		array( 'What is the main advantage of a directional (beam) antenna?', array( 'It is cheaper', 'It concentrates the signal in one direction (gain) and rejects others', 'It works on every band', 'It needs no feeder' ), 1, 'Beams such as Yagis give gain in the wanted direction and reduce signals from the sides and rear.' ),
		array( 'A balun is used to…', array( 'Join a balanced antenna to unbalanced coax', 'Measure power', 'Increase the frequency', 'Store power' ), 0, 'Balun = BALanced-to-UNbalanced. It helps stop common-mode current on the feeder.' ),
		array( 'Which component stores energy in an electric field?', array( 'Resistor', 'Capacitor', 'Inductor', 'Fuse' ), 1, 'Capacitors store energy in an electric field; inductors store it in a magnetic field.' ),
		array( 'What is the purpose of a fuse in the DC lead to a radio?', array( 'To improve audio', 'To protect against excessive current and fire', 'To regulate voltage', 'To filter RF' ), 1, 'A correctly rated fuse protects the wiring and equipment if something goes wrong.' ),
		array( 'What is the frequency of a signal with a wavelength of 40 m?', array( 'About 7.5 MHz', 'About 14 MHz', 'About 3.5 MHz', 'About 40 MHz' ), 0, 'Frequency (MHz) ≈ 300 ÷ wavelength (m): 300 ÷ 40 = 7.5 MHz – hence the 40 m band at 7 MHz.' ),
		array( 'Why is RF exposure a concern near antennas?', array( 'RF can heat body tissue at high power levels and close distances', 'Antennas are radioactive', 'It is not a concern at all', 'It only matters for receivers' ), 0, 'Strong RF fields can heat tissue. Keep antennas away from people and assess exposure as your licence requires.' ),
		array( 'When working a DX station in a pile-up, you should…', array( 'Call continuously until they reply', 'Give your full callsign once or twice, then listen', 'Tell everyone else to stop', 'Transmit on top of the DX station' ), 1, 'Short, clear calls and lots of listening – and follow the DX station’s instructions (e.g. “up 5”).' ),
		array( 'What is “split” operation?', array( 'Using two radios', 'Transmitting on one frequency and listening on another', 'Splitting the band plan', 'Sharing a callsign' ), 1, 'DX stations often listen “up” so callers do not cover their transmit frequency.' ),
	);
	return array_map( function ( $r ) {
		return array( 'q' => $r[0], 'a' => $r[1], 'c' => $r[2], 'e' => $r[3] );
	}, apply_filters( 'hamshack_quiz_questions', $q ) );
}
