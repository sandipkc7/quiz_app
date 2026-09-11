-- ============================================
-- Quiz Application Database Setup
-- Character Set: utf8mb4 (full Unicode support)
-- ============================================

CREATE DATABASE IF NOT EXISTS quiz_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE quiz_db;

-- ============================================
-- Users Table
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Subjects Table
-- ============================================
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    name_local VARCHAR(200) DEFAULT NULL,
    description TEXT,
    icon VARCHAR(10) DEFAULT '📚',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Chapters Table
-- ============================================
CREATE TABLE IF NOT EXISTS chapters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    name_local VARCHAR(300) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Questions Table
-- ============================================
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chapter_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(500) NOT NULL,
    option_b VARCHAR(500) NOT NULL,
    option_c VARCHAR(500) NOT NULL,
    option_d VARCHAR(500) NOT NULL,
    correct_option CHAR(1) NOT NULL,
    explanation TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Quiz Sessions Table
-- ============================================
CREATE TABLE IF NOT EXISTS quiz_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    chapter_id INT NOT NULL,
    score INT DEFAULT 0,
    total_questions INT DEFAULT 20,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Quiz Answers Table
-- ============================================
CREATE TABLE IF NOT EXISTS quiz_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option CHAR(1) DEFAULT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    FOREIGN KEY (session_id) REFERENCES quiz_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SEED DATA
-- ============================================

-- Default admin user (password: admin123)
INSERT INTO users (username, email, password_hash, role) VALUES
('admin', 'admin@quizapp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Subjects
INSERT INTO subjects (name, name_local, description, icon) VALUES
('Science', 'विज्ञान', 'Explore the wonders of science — physics, chemistry, biology and more.', '🔬'),
('Mathematics', 'गणित', 'Sharpen your math skills — algebra, geometry, arithmetic and beyond.', '📐'),
('General Knowledge', 'सामान्य ज्ञान', 'Test your awareness of the world — history, geography, current affairs.', '🌍'),
('Computer Science', 'कम्प्युटर विज्ञान', 'Dive into computing — programming, networks, databases and more.', '💻');

-- Chapters for Science (subject_id = 1)
INSERT INTO chapters (subject_id, name, name_local, sort_order) VALUES
(1, 'Physics Basics', 'भौतिकशास्त्रको आधारभूत', 1),
(1, 'Chemistry Fundamentals', 'रसायनशास्त्रको आधारभूत', 2),
(1, 'Biology Essentials', 'जीवविज्ञानको आवश्यक', 3);

-- Chapters for Mathematics (subject_id = 2)
INSERT INTO chapters (subject_id, name, name_local, sort_order) VALUES
(2, 'Arithmetic', 'अंकगणित', 1),
(2, 'Algebra', 'बीजगणित', 2),
(2, 'Geometry', 'ज्यामिति', 3);

-- Chapters for General Knowledge (subject_id = 3)
INSERT INTO chapters (subject_id, name, name_local, sort_order) VALUES
(3, 'World Geography', 'विश्व भूगोल', 1),
(3, 'History', 'इतिहास', 2);

-- Chapters for Computer Science (subject_id = 4)
INSERT INTO chapters (subject_id, name, name_local, sort_order) VALUES
(4, 'Programming Basics', 'प्रोग्रामिङको आधारभूत', 1),
(4, 'Networking', 'नेटवर्किङ', 2);

-- ============================================
-- Questions for Physics Basics (chapter_id = 1)
-- ============================================
INSERT INTO questions (chapter_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation) VALUES
(1, 'What is the SI unit of force?', 'Joule', 'Newton', 'Pascal', 'Watt', 'B', 'The SI unit of force is the Newton (N), named after Sir Isaac Newton.'),
(1, 'What is the speed of light in vacuum?', '3 × 10⁶ m/s', '3 × 10⁸ m/s', '3 × 10¹⁰ m/s', '3 × 10⁴ m/s', 'B', 'The speed of light in vacuum is approximately 3 × 10⁸ meters per second.'),
(1, 'Which law states that every action has an equal and opposite reaction?', 'First law', 'Second law', 'Third law', 'Law of gravitation', 'C', 'Newton''s Third Law of Motion states this principle.'),
(1, 'What is the unit of electrical resistance?', 'Ampere', 'Volt', 'Ohm', 'Watt', 'C', 'Electrical resistance is measured in Ohms (Ω).'),
(1, 'What type of energy does a moving object have?', 'Potential energy', 'Kinetic energy', 'Thermal energy', 'Chemical energy', 'B', 'A moving object possesses kinetic energy.'),
(1, 'What is the acceleration due to gravity on Earth?', '8.9 m/s²', '9.8 m/s²', '10.8 m/s²', '11.2 m/s²', 'B', 'The standard acceleration due to gravity is approximately 9.8 m/s².'),
(1, 'Which instrument is used to measure atmospheric pressure?', 'Thermometer', 'Barometer', 'Hygrometer', 'Ammeter', 'B', 'A barometer is used to measure atmospheric pressure.'),
(1, 'What is the formula for calculating work done?', 'W = m × g', 'W = F × d', 'W = P × t', 'W = V × I', 'B', 'Work done equals Force multiplied by displacement (W = F × d).'),
(1, 'Sound cannot travel through which medium?', 'Air', 'Water', 'Steel', 'Vacuum', 'D', 'Sound requires a material medium to travel and cannot propagate through vacuum.'),
(1, 'What is the SI unit of power?', 'Joule', 'Newton', 'Watt', 'Pascal', 'C', 'The SI unit of power is the Watt (W).'),
(1, 'Which color of visible light has the longest wavelength?', 'Violet', 'Blue', 'Green', 'Red', 'D', 'Red light has the longest wavelength in the visible spectrum.'),
(1, 'What is the principle behind a hydraulic lift?', 'Archimedes principle', 'Pascal''s law', 'Bernoulli''s principle', 'Newton''s law', 'B', 'Hydraulic lifts work based on Pascal''s law of fluid pressure.'),
(1, 'गुरुत्वाकर्षणको SI एकाइ के हो?', 'न्यूटन', 'जुल', 'वाट', 'पास्कल', 'A', 'गुरुत्वाकर्षण बलको SI एकाइ न्यूटन (N) हो।'),
(1, 'Which phenomenon explains why a pencil appears bent in water?', 'Reflection', 'Refraction', 'Diffraction', 'Dispersion', 'B', 'Refraction of light causes the pencil to appear bent in water.'),
(1, 'What is the freezing point of water in Celsius?', '-100°C', '0°C', '32°C', '100°C', 'B', 'Water freezes at 0°C (32°F) at standard atmospheric pressure.'),
(1, 'What type of lens is used to correct myopia?', 'Convex lens', 'Concave lens', 'Bifocal lens', 'Cylindrical lens', 'B', 'A concave (diverging) lens is used to correct myopia (near-sightedness).'),
(1, 'What is the law of conservation of energy?', 'Energy can be created', 'Energy can be destroyed', 'Energy can neither be created nor destroyed', 'Energy always increases', 'C', 'The law states that energy can neither be created nor destroyed, only transformed.'),
(1, 'Which force keeps planets in orbit around the sun?', 'Magnetic force', 'Gravitational force', 'Nuclear force', 'Electromagnetic force', 'B', 'Gravitational force keeps planets in their orbits around the sun.'),
(1, 'What is the boiling point of water at sea level?', '90°C', '95°C', '100°C', '110°C', 'C', 'Water boils at 100°C (212°F) at standard atmospheric pressure at sea level.'),
(1, 'Who proposed the theory of relativity?', 'Isaac Newton', 'Albert Einstein', 'Galileo Galilei', 'Niels Bohr', 'B', 'Albert Einstein proposed both the special and general theories of relativity.');

-- ============================================
-- Questions for Chemistry Fundamentals (chapter_id = 2)
-- ============================================
INSERT INTO questions (chapter_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation) VALUES
(2, 'What is the chemical symbol for water?', 'HO', 'H₂O', 'H₂O₂', 'OH', 'B', 'Water is composed of two hydrogen atoms and one oxygen atom: H₂O.'),
(2, 'What is the atomic number of Carbon?', '4', '6', '8', '12', 'B', 'Carbon has 6 protons in its nucleus, giving it an atomic number of 6.'),
(2, 'Which gas is most abundant in Earth''s atmosphere?', 'Oxygen', 'Carbon Dioxide', 'Nitrogen', 'Hydrogen', 'C', 'Nitrogen makes up about 78% of Earth''s atmosphere.'),
(2, 'What is the pH of a neutral solution?', '0', '5', '7', '14', 'C', 'A pH of 7 indicates a neutral solution.'),
(2, 'What is the chemical formula for table salt?', 'NaCl', 'KCl', 'CaCl₂', 'MgCl₂', 'A', 'Table salt is sodium chloride (NaCl).'),
(2, 'Which element has the symbol "Fe"?', 'Fluorine', 'Francium', 'Iron', 'Fermium', 'C', 'Fe comes from the Latin word "Ferrum" meaning iron.'),
(2, 'What type of bond involves sharing of electrons?', 'Ionic bond', 'Covalent bond', 'Metallic bond', 'Hydrogen bond', 'B', 'In covalent bonds, atoms share electron pairs.'),
(2, 'Which acid is found in the human stomach?', 'Sulfuric acid', 'Nitric acid', 'Hydrochloric acid', 'Acetic acid', 'C', 'The stomach produces hydrochloric acid (HCl) for digestion.'),
(2, 'What is the lightest element in the periodic table?', 'Helium', 'Lithium', 'Hydrogen', 'Carbon', 'C', 'Hydrogen is the lightest element with an atomic mass of approximately 1.'),
(2, 'What is an isotope?', 'Atoms with same protons but different neutrons', 'Atoms with same neutrons but different protons', 'Atoms with no neutrons', 'Atoms with no electrons', 'A', 'Isotopes are atoms of the same element with different numbers of neutrons.'),
(2, 'रसायनशास्त्रमा "mol" ले के जनाउँछ?', 'एउटा अणु', '6.022 × 10²³ कणहरू', '1 ग्राम पदार्थ', '1 लिटर ग्यास', 'B', 'एक मोलमा 6.022 × 10²³ कणहरू (अवोगाड्रो संख्या) हुन्छन्।'),
(2, 'Which gas is produced during photosynthesis?', 'Carbon Dioxide', 'Nitrogen', 'Oxygen', 'Hydrogen', 'C', 'Plants produce oxygen during the process of photosynthesis.'),
(2, 'What is the valency of Oxygen?', '1', '2', '3', '4', 'B', 'Oxygen has a valency of 2.'),
(2, 'What is rusting of iron an example of?', 'Physical change', 'Chemical change', 'Nuclear change', 'No change', 'B', 'Rusting involves a chemical reaction between iron, oxygen, and moisture.'),
(2, 'Which noble gas is used in fluorescent lighting?', 'Helium', 'Neon', 'Argon', 'Krypton', 'C', 'Argon is commonly used in fluorescent tubes and light bulbs.'),
(2, 'What is the chemical formula for baking soda?', 'Na₂CO₃', 'NaHCO₃', 'NaOH', 'NaCl', 'B', 'Baking soda is sodium bicarbonate (NaHCO₃).'),
(2, 'What particle carries a negative charge?', 'Proton', 'Neutron', 'Electron', 'Photon', 'C', 'Electrons carry a negative electrical charge.'),
(2, 'Which metal is liquid at room temperature?', 'Aluminum', 'Mercury', 'Lead', 'Zinc', 'B', 'Mercury (Hg) is the only metal that is liquid at room temperature.'),
(2, 'How many elements are in the periodic table (as of 2024)?', '108', '112', '118', '120', 'C', 'As of 2024, there are 118 confirmed elements in the periodic table.'),
(2, 'What is the process of a solid turning directly into gas called?', 'Evaporation', 'Sublimation', 'Condensation', 'Deposition', 'B', 'Sublimation is the transition from solid to gas without passing through liquid.');

-- ============================================
-- Questions for Biology Essentials (chapter_id = 3)
-- ============================================
INSERT INTO questions (chapter_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation) VALUES
(3, 'What is the powerhouse of the cell?', 'Nucleus', 'Ribosome', 'Mitochondria', 'Golgi body', 'C', 'Mitochondria generate most of the cell''s supply of ATP, the energy currency.'),
(3, 'What is the basic unit of life?', 'Atom', 'Molecule', 'Cell', 'Organ', 'C', 'The cell is the basic structural and functional unit of all living organisms.'),
(3, 'Which blood group is known as the universal donor?', 'A+', 'B+', 'AB+', 'O-', 'D', 'O-negative blood can be given to anyone regardless of their blood type.'),
(3, 'How many chromosomes does a human cell have?', '23', '44', '46', '48', 'C', 'Human cells contain 46 chromosomes (23 pairs).'),
(3, 'What is the largest organ in the human body?', 'Liver', 'Brain', 'Skin', 'Heart', 'C', 'The skin is the largest organ of the human body.'),
(3, 'Which vitamin is produced when skin is exposed to sunlight?', 'Vitamin A', 'Vitamin B', 'Vitamin C', 'Vitamin D', 'D', 'The body produces Vitamin D when exposed to sunlight.'),
(3, 'What is DNA an abbreviation for?', 'Deoxyribonucleic Acid', 'Dinitrogen Acid', 'Deoxyribose Nucleic Atom', 'Dynamic Nucleic Acid', 'A', 'DNA stands for Deoxyribonucleic Acid.'),
(3, 'Which part of the plant conducts photosynthesis?', 'Root', 'Stem', 'Leaf', 'Flower', 'C', 'Leaves contain chlorophyll and are the primary site of photosynthesis.'),
(3, 'What is the normal human body temperature?', '35°C', '36°C', '37°C', '38°C', 'C', 'Normal human body temperature is approximately 37°C (98.6°F).'),
(3, 'Which organ purifies blood in the human body?', 'Heart', 'Lungs', 'Kidney', 'Liver', 'C', 'Kidneys filter waste products from the blood.'),
(3, 'मानव शरीरमा कति हड्डी हुन्छ?', '106', '206', '306', '406', 'B', 'वयस्क मानव शरीरमा 206 वटा हड्डी हुन्छ।'),
(3, 'What is the function of red blood cells?', 'Fight infections', 'Carry oxygen', 'Clot blood', 'Produce hormones', 'B', 'Red blood cells carry oxygen from the lungs to the body tissues.'),
(3, 'Which enzyme in saliva helps digest starch?', 'Pepsin', 'Lipase', 'Amylase', 'Trypsin', 'C', 'Salivary amylase begins the breakdown of starch into sugars.'),
(3, 'What is the process of cell division called?', 'Osmosis', 'Mitosis', 'Photosynthesis', 'Respiration', 'B', 'Mitosis is the process of cell division that results in two identical daughter cells.'),
(3, 'Which gas do humans exhale?', 'Oxygen', 'Nitrogen', 'Carbon Dioxide', 'Hydrogen', 'C', 'Humans exhale carbon dioxide as a byproduct of cellular respiration.'),
(3, 'What is the largest bone in the human body?', 'Humerus', 'Tibia', 'Femur', 'Spine', 'C', 'The femur (thigh bone) is the longest and largest bone in the human body.'),
(3, 'Which part of the brain controls balance?', 'Cerebrum', 'Cerebellum', 'Medulla', 'Thalamus', 'B', 'The cerebellum coordinates voluntary movements and balance.'),
(3, 'What type of organism is yeast?', 'Bacteria', 'Virus', 'Fungus', 'Protozoa', 'C', 'Yeast is a single-celled fungus.'),
(3, 'How many chambers does the human heart have?', '2', '3', '4', '5', 'C', 'The human heart has 4 chambers: 2 atria and 2 ventricles.'),
(3, 'What is the study of plants called?', 'Zoology', 'Botany', 'Ecology', 'Anatomy', 'B', 'Botany is the scientific study of plants.');

-- ============================================
-- Questions for Arithmetic (chapter_id = 4)
-- ============================================
INSERT INTO questions (chapter_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation) VALUES
(4, 'What is 15% of 200?', '25', '30', '35', '40', 'B', '15% of 200 = (15/100) × 200 = 30.'),
(4, 'What is the square root of 144?', '10', '11', '12', '14', 'C', '√144 = 12, because 12 × 12 = 144.'),
(4, 'If a train travels 60 km in 1 hour, how far will it go in 2.5 hours?', '120 km', '130 km', '140 km', '150 km', 'D', 'Distance = Speed × Time = 60 × 2.5 = 150 km.'),
(4, 'What is the LCM of 12 and 18?', '24', '36', '48', '72', 'B', 'LCM of 12 and 18 is 36.'),
(4, 'What is 3/4 + 1/4?', '1/2', '3/4', '1', '4/4', 'C', '3/4 + 1/4 = 4/4 = 1.'),
(4, 'What is 25 × 25?', '525', '600', '625', '650', 'C', '25 × 25 = 625.'),
(4, 'A number is divisible by 6 if it is divisible by:', '2 and 4', '2 and 3', '3 and 4', '3 and 5', 'B', 'A number must be divisible by both 2 and 3 to be divisible by 6.'),
(4, 'What is the HCF of 24 and 36?', '6', '8', '12', '18', 'C', 'HCF of 24 and 36 is 12.'),
(4, 'If 5x = 35, what is x?', '5', '6', '7', '8', 'C', '5x = 35, so x = 35/5 = 7.'),
(4, 'What is 1000 ÷ 8?', '120', '125', '130', '135', 'B', '1000 ÷ 8 = 125.'),
(4, '२/५ को दशमलवमा के हुन्छ?', '0.2', '0.4', '0.5', '0.25', 'B', '२/५ = 2 ÷ 5 = 0.4'),
(4, 'What is the cube of 5?', '25', '75', '100', '125', 'D', '5³ = 5 × 5 × 5 = 125.'),
(4, 'What fraction is equivalent to 0.75?', '1/2', '2/3', '3/4', '4/5', 'C', '0.75 = 75/100 = 3/4.'),
(4, 'What is the sum of the first 10 natural numbers?', '45', '50', '55', '60', 'C', 'Sum = n(n+1)/2 = 10(11)/2 = 55.'),
(4, 'A shopkeeper offers 20% discount on ₹500. What is the selling price?', '₹380', '₹400', '₹420', '₹450', 'B', 'Discount = 20% of 500 = ₹100. Selling price = 500 - 100 = ₹400.'),
(4, 'What is the value of 2⁵?', '16', '25', '32', '64', 'C', '2⁵ = 2 × 2 × 2 × 2 × 2 = 32.'),
(4, 'If the ratio of boys to girls is 3:5 and total students are 40, how many girls are there?', '15', '20', '25', '30', 'C', 'Girls = (5/8) × 40 = 25.'),
(4, 'What is 17 + 28 + 55?', '90', '95', '100', '105', 'C', '17 + 28 + 55 = 100.'),
(4, 'What is the perimeter of a square with side 7 cm?', '14 cm', '21 cm', '28 cm', '49 cm', 'C', 'Perimeter of square = 4 × side = 4 × 7 = 28 cm.'),
(4, 'What is 999 + 1?', '990', '1000', '1001', '1010', 'B', '999 + 1 = 1000.');
