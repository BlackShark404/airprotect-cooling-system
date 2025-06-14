-- Database schema for AirProTech

-- --------------------------------------
-- 1. Core User and Role Tables
-- --------------------------------------
-- ROLES Table: Defines user roles (customer, technician, admin)
CREATE TABLE USER_ROLE (
    UR_ID    SERIAL PRIMARY KEY,
    UR_NAME  VARCHAR(20) UNIQUE NOT NULL
);


-- Insert role values
INSERT INTO USER_ROLE (UR_NAME) VALUES 
('customer'),
('technician'),
('admin');


-- USERS Table: Base table for all user types
CREATE TABLE USER_ACCOUNT (
    UA_ID                          SERIAL PRIMARY KEY,
    UA_PROFILE_URL                 VARCHAR(255),
    UA_FIRST_NAME                  VARCHAR(255) NOT NULL,
    UA_LAST_NAME                   VARCHAR(255) NOT NULL,
    UA_ADDRESS                     TEXT,
    UA_EMAIL                       VARCHAR(255) UNIQUE NOT NULL,
    UA_HASHED_PASSWORD             VARCHAR(255) NOT NULL,
    UA_PHONE_NUMBER                VARCHAR(20),
    UA_ROLE_ID                     INT NOT NULL,
    UA_IS_ACTIVE                   BOOLEAN DEFAULT TRUE,
    UA_REMEMBER_TOKEN              VARCHAR(255),
    UA_REMEMBER_TOKEN_EXPIRES_AT   TIMESTAMP,
    UA_LAST_LOGIN                  TIMESTAMP,
    UA_CREATED_AT                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UA_UPDATED_AT                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UA_DELETED_AT                  TIMESTAMP,
    CONSTRAINT FK_USER_ROLE FOREIGN KEY (UA_ROLE_ID)
        REFERENCES USER_ROLE(UR_ID) ON DELETE RESTRICT ON UPDATE CASCADE
);


-- CUSTOMER Table: Specific attributes for customer users
CREATE TABLE CUSTOMER (
    CU_ACCOUNT_ID         INT PRIMARY KEY,
    CU_TOTAL_BOOKINGS     INT DEFAULT 0,
    CU_ACTIVE_BOOKINGS    INT DEFAULT 0,
    CU_PENDING_SERVICES   INT DEFAULT 0,
    CU_COMPLETED_SERVICES INT DEFAULT 0,
    CU_PRODUCT_ORDERS     INT DEFAULT 0,
    CU_CREATED_AT         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CU_UPDATED_AT         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_CUSTOMER_ACCOUNT FOREIGN KEY (CU_ACCOUNT_ID)
        REFERENCES USER_ACCOUNT(UA_ID) ON DELETE CASCADE ON UPDATE CASCADE
);


-- ADMIN Table: Specific attributes for admin users
CREATE TABLE ADMIN (
    AD_ACCOUNT_ID       INT PRIMARY KEY,
    AD_OFFICE_NO        VARCHAR(20),
    AD_CREATED_AT       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    AD_UPDATED_AT       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_ADMIN_ACCOUNT FOREIGN KEY (AD_ACCOUNT_ID)
        REFERENCES USER_ACCOUNT(UA_ID) ON DELETE CASCADE ON UPDATE CASCADE
);


-- TECHNICIAN Table: Specific attributes for technician users
CREATE TABLE TECHNICIAN (
    TE_ACCOUNT_ID       INT PRIMARY KEY,
    TE_IS_AVAILABLE     BOOLEAN DEFAULT TRUE,
    TE_CREATED_AT       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    TE_UPDATED_AT       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_TECHNICIAN_ACCOUNT FOREIGN KEY (TE_ACCOUNT_ID)
        REFERENCES USER_ACCOUNT(UA_ID) ON DELETE CASCADE ON UPDATE CASCADE
);


-- --------------------------------------
-- 2. Service-Related Tables
-- --------------------------------------
-- SERVICE_TYPE Table: Defines available service types
CREATE TABLE SERVICE_TYPE (
    ST_ID          SERIAL PRIMARY KEY,
    ST_CODE        VARCHAR(50) UNIQUE NOT NULL,
    ST_NAME        VARCHAR(100) NOT NULL,
    ST_DESCRIPTION TEXT,
    ST_IS_ACTIVE   BOOLEAN DEFAULT TRUE,
    ST_CREATED_AT  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ST_UPDATED_AT  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- Insert service types
INSERT INTO SERVICE_TYPE (ST_CODE, ST_NAME, ST_DESCRIPTION) VALUES
('checkup-repair', 'Aircon Check-up & Repair', 'Diagnostic and repair services for air conditioning units'),
('installation', 'Installation of Units', 'Professional installation of new air conditioning units'),
('ducting', 'Ducting Works', 'Installation and maintenance of air conditioning ducts'),
('cleaning-pms', 'General Cleaning & PMS', 'General cleaning and preventive maintenance services'),
('survey-estimation', 'Survey & Estimation', 'On-site evaluation and cost estimation for air conditioning needs'),
('project-quotations', 'Project Quotations', 'Detailed quotations for air conditioning projects');

-- SERVICE_BOOKING Table: Records customer service requests
CREATE TABLE SERVICE_BOOKING (
    SB_ID               SERIAL PRIMARY KEY,
    SB_CUSTOMER_ID      INT NOT NULL,
    SB_SERVICE_TYPE_ID  INT NOT NULL,
    SB_PREFERRED_DATE   DATE NOT NULL,
	SB_PREFERRED_TIME   TIME NOT NULL,
    SB_ADDRESS          TEXT NOT NULL,
    SB_DESCRIPTION      TEXT NOT NULL,
    SB_STATUS           VARCHAR(20) DEFAULT 'pending',
    SB_PRIORITY         VARCHAR(10) DEFAULT 'moderate',
    SB_ESTIMATED_COST   DECIMAL(10, 2) DEFAULT 0,
    SB_CREATED_AT       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    SB_UPDATED_AT       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    SB_DELETED_AT       TIMESTAMP,
    CONSTRAINT FK_BOOKING_CUSTOMER FOREIGN KEY (SB_CUSTOMER_ID)
        REFERENCES CUSTOMER(CU_ACCOUNT_ID) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT FK_BOOKING_SERVICE_TYPE FOREIGN KEY (SB_SERVICE_TYPE_ID)
        REFERENCES SERVICE_TYPE(ST_ID) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT CK_BOOKING_STATUS CHECK (SB_STATUS IN ('pending', 'confirmed', 'in-progress', 'completed', 'cancelled')),
    CONSTRAINT CK_BOOKING_PRIORITY CHECK (SB_PRIORITY IN ('normal', 'moderate', 'urgent'))
);


-- BOOKING_ASSIGNMENT Table: Assigns technicians to service bookings
CREATE TABLE BOOKING_ASSIGNMENT (
    BA_ID               SERIAL PRIMARY KEY,
    BA_BOOKING_ID       INT NOT NULL,
    BA_TECHNICIAN_ID    INT NOT NULL,
    BA_STATUS           VARCHAR(20) DEFAULT 'assigned',
    BA_NOTES            TEXT,
    BA_ASSIGNED_AT      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    BA_STARTED_AT       TIMESTAMP,
    BA_COMPLETED_AT     TIMESTAMP,
    BA_UPDATED_AT       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_ASSIGNMENT_BOOKING FOREIGN KEY (BA_BOOKING_ID)
        REFERENCES SERVICE_BOOKING(SB_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT FK_ASSIGNMENT_TECHNICIAN FOREIGN KEY (BA_TECHNICIAN_ID)
        REFERENCES TECHNICIAN(TE_ACCOUNT_ID) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT UQ_BOOKING_TECHNICIAN UNIQUE (BA_BOOKING_ID, BA_TECHNICIAN_ID),
    CONSTRAINT CK_ASSIGNMENT_STATUS CHECK (BA_STATUS IN ('assigned', 'in-progress', 'completed', 'cancelled'))
);


-- --------------------------------------
-- 3. Product-Related Tables
-- --------------------------------------
-- PRODUCT Table: Stores product information
CREATE TABLE PRODUCT (
    PROD_ID              SERIAL PRIMARY KEY,
    PROD_IMAGE           TEXT NOT NULL,
    PROD_NAME            VARCHAR(100) NOT NULL,
    PROD_DESCRIPTION     TEXT,
    PROD_CREATED_AT      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PROD_UPDATED_AT      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PROD_DELETED_AT      TIMESTAMP
);


-- PRODUCT_FEATURE Table: Stores product features
CREATE TABLE PRODUCT_FEATURE (
    FEATURE_ID         SERIAL PRIMARY KEY,
    FEATURE_NAME       VARCHAR(100) NOT NULL,
    PROD_ID            INTEGER NOT NULL,
    FEATURE_CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FEATURE_UPDATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FEATURE_DELETED_AT TIMESTAMP,
    FOREIGN KEY (PROD_ID) REFERENCES PRODUCT(PROD_ID) ON DELETE CASCADE
);


-- PRODUCT_SPEC Table: Stores product specifications
CREATE TABLE PRODUCT_SPEC (
    SPEC_ID         SERIAL PRIMARY KEY,
    SPEC_NAME       VARCHAR(100) NOT NULL,
    SPEC_VALUE      VARCHAR(100) NOT NULL,
    PROD_ID         INTEGER NOT NULL,
    SPEC_CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    SPEC_UPDATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    SPEC_DELETED_AT TIMESTAMP,
    FOREIGN KEY (PROD_ID) REFERENCES PRODUCT(PROD_ID) ON DELETE CASCADE
);


CREATE TABLE PRODUCT_VARIANT (
    VAR_ID                          SERIAL PRIMARY KEY,
    VAR_CAPACITY                    VARCHAR(20) NOT NULL,
    VAR_SRP_PRICE                   DECIMAL(10, 2) NOT NULL,

    -- Discounts
    VAR_DISCOUNT_FREE_INSTALL_PCT   DECIMAL(5, 2) DEFAULT 0.00,  -- e.g., 15.00
    VAR_DISCOUNT_WITH_INSTALL_PCT   DECIMAL(5, 2) DEFAULT 0.00,  -- e.g., 25.00

    -- Installation Fee (used only for 'with install' pricing)
    VAR_INSTALLATION_FEE            DECIMAL(10, 2) DEFAULT 0.00,

    -- Computed Prices
    VAR_PRICE_FREE_INSTALL          DECIMAL(10, 2) GENERATED ALWAYS AS 
                                    (VAR_SRP_PRICE * (1 - VAR_DISCOUNT_FREE_INSTALL_PCT / 100)) STORED,

    VAR_PRICE_WITH_INSTALL          DECIMAL(10, 2) GENERATED ALWAYS AS 
                                    ((VAR_SRP_PRICE * (1 - VAR_DISCOUNT_WITH_INSTALL_PCT / 100)) + VAR_INSTALLATION_FEE) STORED,

    VAR_POWER_CONSUMPTION           VARCHAR(20),
    PROD_ID                         INTEGER NOT NULL,
    VAR_CREATED_AT                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    VAR_UPDATED_AT                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    VAR_DELETED_AT                  TIMESTAMP,

    FOREIGN KEY (PROD_ID) REFERENCES PRODUCT(PROD_ID) ON DELETE CASCADE
);


-- PRODUCT_BOOKING Table: Records customer product bookings (formerly PRODUCT_ORDER)
CREATE TABLE PRODUCT_BOOKING (
    PB_ID               SERIAL PRIMARY KEY,
    PB_CUSTOMER_ID      INT NOT NULL,
    PB_VARIANT_ID       INT NOT NULL,
    PB_QUANTITY         INT	 NOT NULL CHECK (PB_QUANTITY > 0),
    PB_UNIT_PRICE       DECIMAL(10, 2) NOT NULL,
    PB_TOTAL_AMOUNT     DECIMAL(10, 2) GENERATED ALWAYS AS (PB_QUANTITY * PB_UNIT_PRICE) STORED,
    PB_STATUS           VARCHAR(20) DEFAULT 'pending',

    -- free_install → VAR_PRICE_FREE_INSTALL
    -- with_install → VAR_PRICE_WITH_INSTALL
    PB_PRICE_TYPE VARCHAR(20) NOT NULL CHECK (PB_PRICE_TYPE IN ('free_install', 'with_install')),
   
    PB_ORDER_DATE       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	PB_PREFERRED_DATE   DATE NOT NULL,
	PB_PREFERRED_TIME   TIME NOT NULL,
	PB_ADDRESS          TEXT NOT NULL,
	PB_DESCRIPTION      TEXT,
    PB_CREATED_AT       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PB_UPDATED_AT       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PB_DELETED_AT       TIMESTAMP,
    
    CONSTRAINT FK_BOOKING_CUSTOMER FOREIGN KEY (PB_CUSTOMER_ID)
        REFERENCES CUSTOMER(CU_ACCOUNT_ID) ON DELETE RESTRICT ON UPDATE CASCADE,
        
    CONSTRAINT FK_BOOKING_VARIANT FOREIGN KEY (PB_VARIANT_ID)
        REFERENCES PRODUCT_VARIANT(VAR_ID) ON DELETE RESTRICT ON UPDATE CASCADE,
        
    CONSTRAINT CK_BOOKING_STATUS CHECK (PB_STATUS IN ('pending', 'confirmed', 'in-progress', 'completed', 'cancelled'))
);


-- PRODUCT_ASSIGNMENT Table: Assigns technicians to product bookings
CREATE TABLE PRODUCT_ASSIGNMENT (
    PA_ID               SERIAL PRIMARY KEY,
    PA_ORDER_ID         INT NOT NULL,
    PA_TECHNICIAN_ID    INT NOT NULL,
    PA_STATUS           VARCHAR(20) DEFAULT 'assigned',
    PA_NOTES            TEXT,
    PA_ASSIGNED_AT      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PA_STARTED_AT       TIMESTAMP,
    PA_COMPLETED_AT     TIMESTAMP,
    PA_UPDATED_AT       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT FK_ASSIGNMENT_BOOKING FOREIGN KEY (PA_ORDER_ID)
        REFERENCES PRODUCT_BOOKING(PB_ID) ON DELETE CASCADE ON UPDATE CASCADE,
        
    CONSTRAINT FK_ASSIGNMENT_TECHNICIAN FOREIGN KEY (PA_TECHNICIAN_ID)
        REFERENCES TECHNICIAN(TE_ACCOUNT_ID) ON DELETE RESTRICT ON UPDATE CASCADE,
        
    CONSTRAINT UQ_ORDER_TECHNICIAN UNIQUE (PA_ORDER_ID, PA_TECHNICIAN_ID),
    
    CONSTRAINT CK_ASSIGNMENT_STATUS CHECK (PA_STATUS IN ('assigned', 'in-progress', 'completed', 'cancelled'))
);



-- --------------------------------------
-- 4. Inventory Management Tables
-- --------------------------------------
-- WAREHOUSE Table: Stores warehouse information
CREATE TABLE WAREHOUSE (
    WHOUSE_ID               SERIAL PRIMARY KEY,
    WHOUSE_NAME             VARCHAR(100),
    WHOUSE_LOCATION         TEXT,
    WHOUSE_STORAGE_CAPACITY INT,
    WHOUSE_RESTOCK_THRESHOLD INT,
    WHOUSE_CREATED_AT       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    WHOUSE_UPDATED_AT       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    WHOUSE_DELETED_AT       TIMESTAMP
);


-- INVENTORY Table: Tracks product stock in warehouses
CREATE TABLE INVENTORY (
    INVE_ID         SERIAL PRIMARY KEY,
    VAR_ID          INT REFERENCES PRODUCT_VARIANT(VAR_ID) ON DELETE CASCADE,
    WHOUSE_ID       INT REFERENCES WAREHOUSE(WHOUSE_ID) ON DELETE CASCADE,
    INVE_TYPE       VARCHAR(50) CHECK (
        INVE_TYPE IN (
            'Regular', 
            'Display', 
            'Reserve', 
            'Damaged', 
            'Returned', 
            'Quarantine'
        )
    ),
    QUANTITY        INT CHECK (QUANTITY >= 0),
    INVE_CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INVE_UPDATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INVE_DELETED_AT TIMESTAMP
);


-- --------------------------------------
-- 1. Triggers for User and Role Management
-- --------------------------------------
-- Trigger to create role-specific record when a user is created
CREATE OR REPLACE FUNCTION create_role_specific_record()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.UA_ROLE_ID = (SELECT UR_ID FROM USER_ROLE WHERE UR_NAME = 'customer') THEN
        INSERT INTO CUSTOMER (CU_ACCOUNT_ID) VALUES (NEW.UA_ID);
    ELSIF NEW.UA_ROLE_ID = (SELECT UR_ID FROM USER_ROLE WHERE UR_NAME = 'technician') THEN
        INSERT INTO TECHNICIAN (TE_ACCOUNT_ID) VALUES (NEW.UA_ID);
    ELSIF NEW.UA_ROLE_ID = (SELECT UR_ID FROM USER_ROLE WHERE UR_NAME = 'admin') THEN
        INSERT INTO ADMIN (AD_ACCOUNT_ID) VALUES (NEW.UA_ID);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER create_role_record_after_user_insert
AFTER INSERT ON USER_ACCOUNT
FOR EACH ROW
EXECUTE FUNCTION create_role_specific_record();

CREATE OR REPLACE FUNCTION update_service_booking_status()
RETURNS TRIGGER AS $$
DECLARE
    total_assignments   INT;
    completed_count     INT;
    in_progress_count   INT;
    assigned_count      INT;
    cancelled_count     INT;
BEGIN
    -- Count per-status and total assignments
    SELECT 
        COUNT(*) INTO total_assignments
    FROM BOOKING_ASSIGNMENT
    WHERE BA_BOOKING_ID = NEW.BA_BOOKING_ID;

    SELECT 
        COUNT(*) INTO completed_count
    FROM BOOKING_ASSIGNMENT
    WHERE BA_BOOKING_ID = NEW.BA_BOOKING_ID AND BA_STATUS = 'completed';

    SELECT 
        COUNT(*) INTO in_progress_count
    FROM BOOKING_ASSIGNMENT
    WHERE BA_BOOKING_ID = NEW.BA_BOOKING_ID AND BA_STATUS = 'in-progress';

    SELECT 
        COUNT(*) INTO assigned_count
    FROM BOOKING_ASSIGNMENT
    WHERE BA_BOOKING_ID = NEW.BA_BOOKING_ID AND BA_STATUS = 'assigned';

    SELECT 
        COUNT(*) INTO cancelled_count
    FROM BOOKING_ASSIGNMENT
    WHERE BA_BOOKING_ID = NEW.BA_BOOKING_ID AND BA_STATUS = 'cancelled';

    -- Decide SERVICE_BOOKING status
    IF total_assignments = completed_count AND total_assignments > 0 THEN
        UPDATE SERVICE_BOOKING SET SB_STATUS = 'completed', SB_UPDATED_AT = CURRENT_TIMESTAMP
        WHERE SB_ID = NEW.BA_BOOKING_ID;

    ELSIF in_progress_count > 0 THEN
        UPDATE SERVICE_BOOKING SET SB_STATUS = 'in-progress', SB_UPDATED_AT = CURRENT_TIMESTAMP
        WHERE SB_ID = NEW.BA_BOOKING_ID;

    ELSIF assigned_count > 0 THEN
        UPDATE SERVICE_BOOKING SET SB_STATUS = 'confirmed', SB_UPDATED_AT = CURRENT_TIMESTAMP
        WHERE SB_ID = NEW.BA_BOOKING_ID;

    ELSIF total_assignments = cancelled_count AND total_assignments > 0 THEN
        UPDATE SERVICE_BOOKING SET SB_STATUS = 'cancelled', SB_UPDATED_AT = CURRENT_TIMESTAMP
        WHERE SB_ID = NEW.BA_BOOKING_ID;

    ELSE
        UPDATE SERVICE_BOOKING SET SB_STATUS = 'pending', SB_UPDATED_AT = CURRENT_TIMESTAMP
        WHERE SB_ID = NEW.BA_BOOKING_ID;
    END IF;

    RETURN NULL;
END;
$$ LANGUAGE plpgsql;


CREATE OR REPLACE FUNCTION update_product_booking_status()
RETURNS TRIGGER AS $$
DECLARE
    total_assignments   INT;
    completed_count     INT;
    in_progress_count   INT;
    assigned_count      INT;
    cancelled_count     INT;
BEGIN
    -- Count total and per-status assignments
    SELECT COUNT(*) INTO total_assignments
    FROM PRODUCT_ASSIGNMENT
    WHERE PA_ORDER_ID = NEW.PA_ORDER_ID;

    SELECT COUNT(*) INTO completed_count
    FROM PRODUCT_ASSIGNMENT
    WHERE PA_ORDER_ID = NEW.PA_ORDER_ID AND PA_STATUS = 'completed';

    SELECT COUNT(*) INTO in_progress_count
    FROM PRODUCT_ASSIGNMENT
    WHERE PA_ORDER_ID = NEW.PA_ORDER_ID AND PA_STATUS = 'in-progress';

    SELECT COUNT(*) INTO assigned_count
    FROM PRODUCT_ASSIGNMENT
    WHERE PA_ORDER_ID = NEW.PA_ORDER_ID AND PA_STATUS = 'assigned';

    SELECT COUNT(*) INTO cancelled_count
    FROM PRODUCT_ASSIGNMENT
    WHERE PA_ORDER_ID = NEW.PA_ORDER_ID AND PA_STATUS = 'cancelled';

    -- Determine and update PB_STATUS in PRODUCT_BOOKING
    IF total_assignments = completed_count AND total_assignments > 0 THEN
        UPDATE PRODUCT_BOOKING SET PB_STATUS = 'completed', PB_UPDATED_AT = CURRENT_TIMESTAMP
        WHERE PB_ID = NEW.PA_ORDER_ID;

    ELSIF in_progress_count > 0 THEN
        UPDATE PRODUCT_BOOKING SET PB_STATUS = 'in-progress', PB_UPDATED_AT = CURRENT_TIMESTAMP
        WHERE PB_ID = NEW.PA_ORDER_ID;

    ELSIF assigned_count > 0 THEN
        UPDATE PRODUCT_BOOKING SET PB_STATUS = 'confirmed', PB_UPDATED_AT = CURRENT_TIMESTAMP
        WHERE PB_ID = NEW.PA_ORDER_ID;

    ELSIF total_assignments = cancelled_count AND total_assignments > 0 THEN
        UPDATE PRODUCT_BOOKING SET PB_STATUS = 'cancelled', PB_UPDATED_AT = CURRENT_TIMESTAMP
        WHERE PB_ID = NEW.PA_ORDER_ID;

    ELSE
        UPDATE PRODUCT_BOOKING SET PB_STATUS = 'pending', PB_UPDATED_AT = CURRENT_TIMESTAMP
        WHERE PB_ID = NEW.PA_ORDER_ID;
    END IF;

    RETURN NULL;
END;
$$ LANGUAGE plpgsql;


CREATE TRIGGER trg_update_service_booking_status
AFTER INSERT OR UPDATE OF BA_STATUS OR DELETE
ON BOOKING_ASSIGNMENT
FOR EACH ROW
EXECUTE FUNCTION update_service_booking_status();


CREATE TRIGGER trg_update_product_booking_status
AFTER INSERT OR UPDATE OF PA_STATUS OR DELETE
ON PRODUCT_ASSIGNMENT
FOR EACH ROW
EXECUTE FUNCTION update_product_booking_status();

-- --------------------------------------
-- 3. Warehouse Capacity Management Trigger
-- --------------------------------------
-- Trigger to check if adding inventory would exceed warehouse capacity
CREATE OR REPLACE FUNCTION check_warehouse_capacity()
RETURNS TRIGGER AS $$
DECLARE
    current_inventory    INT;
    warehouse_capacity   INT;
    warehouse_name       VARCHAR(100);
    final_quantity       INT;
BEGIN
    -- Get the warehouse capacity
    SELECT WHOUSE_STORAGE_CAPACITY, WHOUSE_NAME INTO warehouse_capacity, warehouse_name
    FROM WAREHOUSE 
    WHERE WHOUSE_ID = NEW.WHOUSE_ID AND WHOUSE_DELETED_AT IS NULL;
    
    -- If warehouse doesn't have a defined capacity, allow the operation
    IF warehouse_capacity IS NULL OR warehouse_capacity <= 0 THEN
        RETURN NEW;
    END IF;

    -- Calculate current inventory in this warehouse
    SELECT COALESCE(SUM(QUANTITY), 0) INTO current_inventory
    FROM INVENTORY
    WHERE WHOUSE_ID = NEW.WHOUSE_ID AND INVE_DELETED_AT IS NULL
    AND INVE_ID != NEW.INVE_ID; -- Exclude this record for updates
    
    -- Calculate final quantity after this operation
    final_quantity := current_inventory + NEW.QUANTITY;
    
    -- Check if this would exceed capacity
    IF final_quantity > warehouse_capacity THEN
        RAISE EXCEPTION 'Cannot add % items to warehouse "%" (ID: %). This would exceed the warehouse capacity of % items. Current inventory: % items.', 
                        NEW.QUANTITY, warehouse_name, NEW.WHOUSE_ID, warehouse_capacity, current_inventory;
    END IF;

    -- If all is well, allow the operation
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Create the trigger on the INVENTORY table
CREATE TRIGGER trg_check_warehouse_capacity
BEFORE INSERT OR UPDATE OF QUANTITY, WHOUSE_ID ON INVENTORY
FOR EACH ROW
EXECUTE FUNCTION check_warehouse_capacity();